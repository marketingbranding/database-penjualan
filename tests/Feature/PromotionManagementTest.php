<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\PaymentMethod;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_promotion_with_multiple_targets(): void
    {
        $admin = $this->admin();
        $branch = Branch::factory()->create(['name' => 'Magelang']);
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'B']);

        $this->actingAs($admin)
            ->post(route('admin.promotions.store'), [
                'promo_id' => 'CUSTOM-MANUAL-ID',
                'name' => 'DP 10jt All In',
                'start_date' => today()->subDay()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
                'description' => 'Blok B - Blok J',
                'targets' => [
                    ['type' => 'branch', 'target_id' => $branch->id],
                    ['type' => 'project', 'target_id' => $project->id],
                    ['type' => 'block', 'target_id' => $block->id],
                ],
            ])
            ->assertRedirect(route('admin.promotions.edit', Promotion::firstOrFail()));

        $promotion = Promotion::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^PROMO-\d{6}-\d{4}$/', $promotion->promo_id);
        $this->assertDatabaseHas('promotion_targets', ['promotion_id' => $promotion->id, 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('promotion_targets', ['promotion_id' => $promotion->id, 'project_id' => $project->id]);
        $this->assertDatabaseHas('promotion_targets', ['promotion_id' => $promotion->id, 'project_block_id' => $block->id]);
    }

    public function test_admin_promotion_forms_render_successfully(): void
    {
        $admin = $this->admin();
        $promotion = Promotion::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.promotions.create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.promotions.edit', $promotion))
            ->assertOk();
    }

    public function test_generated_promo_ids_are_unique(): void
    {
        $admin = $this->admin();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.promotions.store'), $this->promotionPayload($branch))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.promotions.store'), $this->promotionPayload($branch, ['name' => 'Promo kedua']))
            ->assertRedirect();

        $this->assertSame(2, Promotion::query()->count());
        $this->assertSame(2, Promotion::query()->distinct('promo_id')->count('promo_id'));
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $admin = $this->admin();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.promotions.store'), $this->promotionPayload($branch, [
                'start_date' => today()->toDateString(),
                'end_date' => today()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_promotion_used_by_sales_case_cannot_be_deleted(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $promotion = $this->activeBranchPromotion($admin->branch);

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot, promotionId: $promotion->id))
            ->assertRedirect();

        $superAdmin = $this->admin();

        $this->actingAs($superAdmin)
            ->delete(route('admin.promotions.destroy', $promotion))
            ->assertSessionHasErrors('promotion');

        $this->assertDatabaseHas('promotions', ['id' => $promotion->id]);
    }

    public function test_consumer_can_be_created_with_matching_promotion(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $promotion = $this->activeBranchPromotion($admin->branch);

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot, promotionId: $promotion->id))
            ->assertRedirect();

        $this->assertDatabaseHas('sales_cases', [
            'lot_id' => $lot->id,
            'promotion_id' => $promotion->id,
        ]);
    }

    public function test_non_matching_promotion_is_rejected_on_store(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $otherBranch = Branch::factory()->create(['name' => 'Semarang']);
        $promotion = $this->activeBranchPromotion($otherBranch);

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot, promotionId: $promotion->id))
            ->assertSessionHasErrors('promotion_id');

        $this->assertDatabaseCount('sales_cases', 0);
    }

    public function test_inactive_promotion_is_rejected_on_store(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $promotion = $this->activeBranchPromotion($admin->branch, [
            'start_date' => today()->subDays(20)->toDateString(),
            'end_date' => today()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot, promotionId: $promotion->id))
            ->assertSessionHasErrors('promotion_id');
    }

    public function test_ended_promotion_is_kept_when_editing_existing_consumer(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $promotion = $this->activeBranchPromotion($admin->branch);

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot, promotionId: $promotion->id))
            ->assertRedirect();

        $promotion->update(['end_date' => today()->subDay()->toDateString()]);
        $salesCase = SalesCase::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('consumers.edit', $salesCase))
            ->assertOk()
            ->assertViewHas('promotions', fn ($promotions) => $promotions->contains('id', $promotion->id));

        $this->actingAs($admin)
            ->put(route('consumers.update', $salesCase), [
                ...$this->payload($project, $lot, promotionId: $promotion->id),
                'no_ktp' => $salesCase->consumer->no_ktp,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($promotion->id, $salesCase->fresh()->promotion_id);
    }

    public function test_create_form_only_exposes_active_promotions(): void
    {
        [$admin] = $this->propertyContext();
        $ended = $this->activeBranchPromotion($admin->branch, [
            'end_date' => today()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('consumers.create'))
            ->assertOk()
            ->assertViewHas('promotions', fn ($promotions) => ! $promotions->contains('id', $ended->id));
    }

    private function admin(): User
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return $admin;
    }

    /** @return array{User, Branch, Project, Lot} */
    private function propertyContext(string $branchName = 'Magelang', string $projectName = 'Marison Kalinegoro', string $lotCode = 'A01'): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => $branchName]);
        $project = Project::factory()->for($branch)->create(['name' => $projectName]);
        $block = ProjectBlock::factory()->for($project)->create(['code' => $lotCode[0]]);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => (int) substr($lotCode, 1),
            'code' => $lotCode,
            'status' => LotStatus::Available,
        ]);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return [$admin, $branch, $project, $lot];
    }

    /** @param array<string, mixed> $overrides */
    private function activeBranchPromotion(Branch $branch, array $overrides = []): Promotion
    {
        $promotion = Promotion::factory()->create($overrides);
        $promotion->targets()->create(['branch_id' => $branch->id]);

        return $promotion;
    }

    /** @param array<string, mixed> $overrides */
    private function promotionPayload(Branch $branch, array $overrides = []): array
    {
        return [
            'name' => 'Promo Contoh',
            'start_date' => today()->subDay()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'description' => null,
            'targets' => [['type' => 'branch', 'target_id' => $branch->id]],
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    private function payload(Project $project, Lot $lot, ?int $promotionId = null, PaymentMethod $method = PaymentMethod::Cash): array
    {
        return [
            'project_id' => $project->id,
            'lot_id' => $lot->id,
            'promotion_id' => $promotionId,
            'no_ktp' => '3308106504650003',
            'nama_konsumen' => 'Rr Arista Widiastuti',
            'tanggal_lahir' => '1965-04-25',
            'pekerjaan' => 'Karyawan Swasta',
            'detail_pekerjaan' => 'Kosong',
            'alamat' => 'Jl Kanon Blok E-15 Jogin I 004/010',
            'kelurahan' => 'Jogonegoro',
            'kecamatan' => 'Mertoyudan',
            'kabupaten_kota' => 'Kabupaten Magelang',
            'no_hp' => '081392267571',
            'nama_kontak_darurat' => 'Arismaya',
            'no_hp_kontak_darurat' => '081338628019',
            'metode_pembayaran' => $method->value,
            'keterangan' => null,
        ];
    }
}
