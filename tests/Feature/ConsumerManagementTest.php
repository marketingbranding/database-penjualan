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

class ConsumerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_only_sees_projects_and_lots_from_assigned_branch(): void
    {
        [$admin, $branch, $project, $lot] = $this->propertyContext('Magelang', 'Kalinegoro', 'A01');
        [, , $otherProject] = $this->propertyContext('Semarang', 'Mijen', 'B01');

        $this->actingAs($admin)
            ->get(route('consumers.create'))
            ->assertOk()
            ->assertSeeText($branch->name)
            ->assertViewHas('projects', fn ($projects) => $projects->contains('id', $project->id)
                && $projects->flatMap->lots->contains('id', $lot->id)
                && ! $projects->contains('id', $otherProject->id));
    }

    public function test_admin_can_create_consumer_and_lot_becomes_reserved(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot))
            ->assertRedirect();

        $consumer = Consumer::query()->firstOrFail();

        $this->assertSame(61, $consumer->age);
        $this->assertDatabaseHas('sales_cases', [
            'consumer_id' => $consumer->id,
            'lot_id' => $lot->id,
            'payment_method' => PaymentMethod::Cash->value,
            'created_by' => $admin->id,
        ]);
        $this->assertSame(LotStatus::Reserved, $lot->fresh()->status);
        $this->actingAs($admin)
            ->get(route('consumers.edit', SalesCase::query()->firstOrFail()))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('consumers.show', SalesCase::query()->firstOrFail()))
            ->assertOk()
            ->assertSeeText('Riwayat pembelian');
    }

    public function test_admin_cannot_use_or_edit_a_lot_from_another_branch(): void
    {
        [$admin] = $this->propertyContext('Magelang', 'Kalinegoro', 'A01');
        [, , $otherProject, $otherLot] = $this->propertyContext('Semarang', 'Mijen', 'B01');

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($otherProject, $otherLot))
            ->assertSessionHasErrors('project_id');

        $otherConsumer = Consumer::factory()->create();
        $otherCase = SalesCase::factory()->for($otherConsumer)->for($otherLot)->create();

        $this->actingAs($admin)
            ->get(route('consumers.edit', $otherCase))
            ->assertForbidden();
    }

    public function test_kpr_consumer_can_only_have_one_lot(): void
    {
        [$admin, , $project, $firstLot] = $this->propertyContext();
        $block = $firstLot->block;
        $secondLot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 2,
            'code' => 'A02',
            'status' => LotStatus::Available,
        ]);

        $firstPayload = $this->payload($project, $firstLot, PaymentMethod::Kpr);
        $this->actingAs($admin)->post(route('consumers.store'), $firstPayload)->assertRedirect();

        $this->actingAs($admin)
            ->post(route('consumers.store'), [
                ...$firstPayload,
                'lot_id' => $secondLot->id,
                'metode_pembayaran' => PaymentMethod::Cash->value,
            ])
            ->assertSessionHasErrors('metode_pembayaran');

        $this->assertDatabaseCount('sales_cases', 1);
        $this->assertSame(LotStatus::Available, $secondLot->fresh()->status);
    }

    public function test_cash_consumer_can_have_multiple_lots(): void
    {
        [$admin, , $project, $firstLot] = $this->propertyContext();
        $block = $firstLot->block;
        $secondLot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 2,
            'code' => 'A02',
            'status' => LotStatus::Available,
        ]);

        $payload = $this->payload($project, $firstLot);
        $this->actingAs($admin)->post(route('consumers.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('consumers.store'), [...$payload, 'lot_id' => $secondLot->id])->assertRedirect();

        $this->assertDatabaseCount('consumers', 1);
        $this->assertDatabaseCount('sales_cases', 2);
    }

    public function test_unavailable_lot_cannot_be_selected_for_new_consumer(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $lot->update(['status' => LotStatus::Sold]);

        $this->actingAs($admin)
            ->post(route('consumers.store'), $this->payload($project, $lot))
            ->assertSessionHasErrors('lot_id');

        $this->assertDatabaseCount('sales_cases', 0);
    }

    public function test_admin_can_export_only_assigned_branch_consumer_data(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $this->actingAs($admin)->post(route('consumers.store'), $this->payload($project, $lot));

        $this->actingAs($admin)
            ->get(route('consumers.export'))
            ->assertOk()
            ->assertDownload('data-konsumen-'.now()->format('Y-m-d').'.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @return array{User, Branch, Project, Lot} */
    private function propertyContext(
        string $branchName = 'Magelang',
        string $projectName = 'Marison Kalinegoro',
        string $lotCode = 'A01',
    ): array {
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

    /** @return array<string, mixed> */
    private function payload(Project $project, Lot $lot, PaymentMethod $method = PaymentMethod::Cash): array
    {
        return [
            'project_id' => $project->id,
            'lot_id' => $lot->id,
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
