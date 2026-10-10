<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\PaymentMethod;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawnVsCancelledTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawn_case_leaves_active_pipeline_but_stays_visible(): void
    {
        [$admin, $salesCase] = $this->reservedSalesCase();

        $this->actingAs($admin)
            ->patch(route('sales-process.sales-cases.withdraw', $salesCase), [
                'reason' => 'Konsumen mundur karena pindah kota.',
            ])
            ->assertRedirect();

        $salesCase->refresh();
        $this->assertSame('MUNDUR', $salesCase->consumer_status);
        $this->assertNull($salesCase->cancelled_at);
        $this->assertSame(0, SalesCase::query()->active()->count());

        $this->actingAs($admin)
            ->get(route('consumers.show', $salesCase))
            ->assertOk()
            ->assertSee('MUNDUR');

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertOk()
            ->assertDontSee($salesCase->consumer->nama_konsumen);
    }

    public function test_withdraw_twice_is_rejected(): void
    {
        [$admin, $salesCase] = $this->reservedSalesCase();

        $this->actingAs($admin)->patch(route('sales-process.sales-cases.withdraw', $salesCase), [
            'reason' => 'Mundur pertama.',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('sales-process.sales-cases.withdraw', $salesCase), [
            'reason' => 'Mundur kedua.',
        ])->assertSessionHasErrors('reason');
    }

    public function test_cancelled_case_still_excluded_from_active(): void
    {
        [$admin, $salesCase] = $this->reservedSalesCase();

        $this->actingAs($admin)->patch(route('consumers.cancel', $salesCase), [
            'reason' => 'Konsumen membatalkan pengajuan secara resmi.',
        ])->assertRedirect();

        $this->assertSame(0, SalesCase::query()->active()->count());
    }

    /** @return array{User, SalesCase} */
    private function reservedSalesCase(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => 'Magelang']);
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'A']);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 1,
            'code' => 'A01',
            'status' => LotStatus::Reserved,
        ]);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);
        $salesCase = SalesCase::factory()->for(Consumer::factory())->for($lot)->for($admin, 'creator')->create([
            'payment_method' => PaymentMethod::Cash,
        ]);

        return [$admin, $salesCase];
    }
}
