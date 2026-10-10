<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\PsjbUtjCase;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\PaymentMethod;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PsjbUtjManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_psjb_and_system_calculates_derived_values(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create([
            'payment_method' => PaymentMethod::Cash,
        ]);

        $this->actingAs($admin)
            ->post(route('sales-process.psjb-utj.store'), [
                'sales_case_id' => $salesCase->id,
                'psjb_date' => '2026-05-02',
                'process_stage' => 'psjb_utj',
                'sp3k_issued' => false,
                'unit_price' => 166000000,
                'utj_date' => '2026-05-02',
                'utj_amount' => 500000,
                'dp_all_in' => 175840000,
                'dp_installment_count' => 3,
                'land_area_m2' => 120,
                'land_price_per_m2' => 1250000,
                'consumer_status' => 'LANJUT',
                'filing_status' => 'INTERNAL',
            ])
            ->assertRedirect();

        $psjb = PsjbUtjCase::query()->firstOrFail();

        $this->assertStringStartsWith('260502-PSJB-CASH-', $psjb->psjb_id);
        $this->assertSame(58613333.33, (float) $psjb->dp_installment_amount);
        $this->assertSame(150000000.0, (float) $psjb->land_total);
        $this->assertDatabaseHas('psjb_utj_cases', [
            'sales_case_id' => $salesCase->id,
            'consumer_status' => 'LANJUT',
            'filing_status' => 'INTERNAL',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $psjb->getMorphClass(),
            'subject_id' => $psjb->id,
            'branch_id' => $admin->branch_id,
            'event' => 'created',
        ]);

        $this->actingAs($admin)
            ->get(route('sales-process.psjb-utj'))
            ->assertOk()
            ->assertSee($psjb->psjb_id)
            ->assertSee($salesCase->consumer->nama_konsumen);
    }

    public function test_sales_case_can_have_only_one_psjb(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        PsjbUtjCase::factory()->for($salesCase)->create();

        $this->actingAs($admin)
            ->get(route('sales-process.psjb-utj.create'))
            ->assertOk()
            ->assertViewHas('salesCases', fn ($salesCases) => ! $salesCases->contains('id', $salesCase->id));
    }

    public function test_psjb_index_filters_by_filing_status(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $internalCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $doneLot = Lot::factory()->for($lot->project)->for($lot->block, 'block')->create([
            'number' => 2,
            'code' => 'A02',
            'status' => LotStatus::Reserved,
        ]);
        $doneCase = SalesCase::factory()->for($doneLot)->for($admin, 'creator')->create();
        $internal = PsjbUtjCase::factory()->for($internalCase)->create(['filing_status' => 'INTERNAL']);
        $done = PsjbUtjCase::factory()->for($doneCase)->create(['filing_status' => 'Done']);

        $this->actingAs($admin)
            ->get(route('sales-process.psjb-utj', ['filing_status' => 'INTERNAL']))
            ->assertOk()
            ->assertSee($internal->psjb_id)
            ->assertDontSee($done->psjb_id);

        $this->actingAs($admin)
            ->get(route('sales-process.psjb-utj', ['filing_status' => 'Done']))
            ->assertOk()
            ->assertSee($done->psjb_id)
            ->assertDontSee($internal->psjb_id);
    }

    public function test_branch_role_without_create_permission_cannot_create_psjb(): void
    {
        [$admin] = $this->propertyContext('Magelang', 'Marison', 'A01');
        [, , , $lot] = $this->propertyContext('Magelang', 'Marison', 'A02');
        $salesCase = SalesCase::factory()->for($lot)->create();
        $spv = User::factory()->for($admin->branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();
        $spv->globalRoles()->attach($role);

        $this->actingAs($spv)
            ->post(route('sales-process.psjb-utj.store'), [
                'sales_case_id' => $salesCase->id,
                'psjb_date' => today()->toDateString(),
                'process_stage' => 'psjb_utj',
                'sp3k_issued' => false,
                'unit_price' => 1,
                'utj_amount' => 1,
                'dp_all_in' => 1,
                'land_area_m2' => 1,
                'land_price_per_m2' => 1,
                'consumer_status' => 'LANJUT',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_psjb_status_and_manual_fields(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $psjb = PsjbUtjCase::factory()->for($salesCase)->create();

        $this->actingAs($admin)
            ->put(route('sales-process.psjb-utj.update', $psjb), [
                'psjb_date' => $psjb->psjb_date->format('Y-m-d'),
                'process_stage' => 'psjb_utj',
                'sp3k_issued' => false,
                'unit_price' => 166000000,
                'utj_amount' => 500000,
                'dp_all_in' => 15000000,
                'dp_installment_count' => 3,
                'land_area_m2' => 100,
                'land_price_per_m2' => 1250000,
                'consumer_status' => 'MUNDUR',
                'filing_status' => 'Done',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'psjb-updated');

        $this->assertDatabaseHas('psjb_utj_cases', [
            'id' => $psjb->id,
            'consumer_status' => 'LANJUT',
            'filing_status' => 'Done',
            'land_total' => 125000000,
        ]);
    }

    public function test_pindah_kavling_before_sp3k_only_changes_the_lot(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $newLot = Lot::factory()->for($project)->for($lot->block, 'block')->create([
            'number' => 2,
            'code' => 'A02',
            'status' => LotStatus::Available,
        ]);
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $psjb = PsjbUtjCase::factory()->for($salesCase)->create(['process_stage' => 'pemberkasan', 'sp3k_issued' => false]);

        $this->actingAs($admin)
            ->post(route('sales-process.psjb-utj.change.store', $psjb), [
                'change_type' => 'pindah_kavling',
                'process_stage' => 'pemberkasan',
                'sp3k_issued' => false,
                'new_lot_id' => $newLot->id,
                'reason' => 'Konsumen memilih kavling lain.',
            ])
            ->assertRedirect();

        $this->assertSame($newLot->id, $salesCase->fresh()->lot_id);
        $this->assertSame(LotStatus::Available, $lot->fresh()->status);
        $this->assertSame(LotStatus::Reserved, $newLot->fresh()->status);
        $this->assertDatabaseHas('sales_case_change_requests', [
            'change_type' => 'pindah_kavling',
            'old_lot_id' => $lot->id,
            'new_lot_id' => $newLot->id,
            'restart_stage' => null,
        ]);
    }

    public function test_pindah_kavling_after_sp3k_restarts_at_proses_bank(): void
    {
        [$admin, , $project, $lot] = $this->propertyContext();
        $newLot = Lot::factory()->for($project)->for($lot->block, 'block')->create([
            'number' => 2,
            'code' => 'A02',
            'status' => LotStatus::Available,
        ]);
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $psjb = PsjbUtjCase::factory()->for($salesCase)->create(['process_stage' => 'proses_bank', 'sp3k_issued' => true]);

        $this->actingAs($admin)
            ->post(route('sales-process.psjb-utj.change.store', $psjb), [
                'change_type' => 'pindah_kavling',
                'process_stage' => 'proses_bank',
                'sp3k_issued' => true,
                'new_lot_id' => $newLot->id,
                'reason' => 'Perubahan kavling setelah SP3K.',
            ])
            ->assertRedirect();

        $this->assertSame('proses_bank', $psjb->fresh()->process_stage);
        $this->assertFalse($psjb->fresh()->sp3k_issued);
        $this->assertDatabaseHas('sales_case_change_requests', [
            'change_type' => 'pindah_kavling',
            'sp3k_issued' => true,
            'restart_stage' => 'proses_bank',
        ]);
    }

    public function test_ganti_nama_creates_new_consumer_and_restarts_from_selected_stage(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $oldConsumerId = $salesCase->consumer_id;
        $psjb = PsjbUtjCase::factory()->for($salesCase)->create(['process_stage' => 'bi_checking']);

        $this->actingAs($admin)
            ->post(route('sales-process.psjb-utj.change.store', $psjb), [
                'change_type' => 'ganti_nama',
                'process_stage' => 'bi_checking',
                'restart_stage' => 'psjb_utj',
                'sp3k_issued' => false,
                'reason' => 'Pengalihan kepada konsumen baru.',
                'new_no_ktp' => '3308105702920003',
                'new_nama_konsumen' => 'Konsumen Baru',
                'new_tanggal_lahir' => '1990-01-01',
                'new_pekerjaan' => 'Karyawan Swasta',
                'new_alamat' => 'Alamat baru',
                'new_kelurahan' => 'Kelurahan Baru',
                'new_kecamatan' => 'Kecamatan Baru',
                'new_kabupaten_kota' => 'Kabupaten Baru',
                'new_no_hp' => '081234567890',
                'new_nama_kontak_darurat' => 'Kontak Baru',
                'new_no_hp_kontak_darurat' => '081234567891',
            ])
            ->assertRedirect();

        $salesCase->refresh();
        $this->assertNotSame($oldConsumerId, $salesCase->consumer_id);
        $this->assertSame('Konsumen Baru', $salesCase->consumer->nama_konsumen);
        $this->assertSame('psjb_utj', $psjb->fresh()->process_stage);
        $this->assertDatabaseHas('sales_case_change_requests', [
            'change_type' => 'ganti_nama',
            'old_consumer_id' => $oldConsumerId,
            'new_consumer_id' => $salesCase->consumer_id,
            'restart_stage' => 'psjb_utj',
        ]);
        $this->assertDatabaseHas('consumers', ['id' => $oldConsumerId]);
    }

    public function test_changes_are_blocked_after_akad_bast(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $psjb = PsjbUtjCase::factory()->for($salesCase)->create(['process_stage' => 'akad_bast']);

        $this->actingAs($admin)
            ->get(route('sales-process.psjb-utj.change.create', $psjb))
            ->assertStatus(422);
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
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return [$admin, $branch, $project, $lot];
    }
}
