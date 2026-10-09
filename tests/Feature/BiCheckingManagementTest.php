<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Bank;
use App\Models\BankUnit;
use App\Models\Branch;
use App\Models\LeadTimeTarget;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiCheckingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_multiple_bi_checkings_for_different_banks(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $first = $this->bankWithUnit('BTN', 'Magelang');
        $second = $this->bankWithUnit('BNI', 'Yogyakarta');

        $payload = [
            'sales_case_id' => $salesCase->id,
            'bank_id' => $first->id,
            'bank_unit_id' => $first->units->first()->id,
            'checking_date' => today()->toDateString(),
            'slik_result' => 'OK',
            'notes' => 'Lancar',
            'bank_decision' => 'Reject',
        ];

        $this->actingAs($admin)->post(route('sales-process.bi-checking.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('sales-process.bi-checking.store'), [...$payload, 'bank_id' => $second->id, 'bank_unit_id' => $second->units->first()->id, 'bank_decision' => 'Lanjut'])->assertRedirect();

        $this->assertDatabaseCount('bi_checkings', 2);
        $this->assertSame('LANJUT', $salesCase->fresh()->consumer_status);
        $this->assertDatabaseHas('bi_checkings', ['bank_id' => $second->id, 'bank_decision' => 'Lanjut']);
    }

    public function test_bank_unit_must_belong_to_selected_bank(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $first = $this->bankWithUnit('BTN', 'Magelang');
        $second = $this->bankWithUnit('BNI', 'Yogyakarta');

        $this->actingAs($admin)
            ->post(route('sales-process.bi-checking.store'), [
                'sales_case_id' => $salesCase->id,
                'bank_id' => $first->id,
                'bank_unit_id' => $second->units->first()->id,
                'checking_date' => today()->toDateString(),
                'slik_result' => 'KOL 5',
                'bank_decision' => 'Reject',
            ])
            ->assertStatus(422);
    }

    public function test_withdraw_releases_reserved_lot_before_akad(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $lot->update(['status' => LotStatus::Reserved]);
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();

        $this->actingAs($admin)
            ->patch(route('sales-process.sales-cases.withdraw', $salesCase), ['reason' => 'Konsumen membatalkan pengajuan.'])
            ->assertRedirect();

        $this->assertSame('MUNDUR', $salesCase->fresh()->consumer_status);
        $this->assertSame(LotStatus::Available, $lot->fresh()->status);
        $this->assertDatabaseHas('sales_case_status_histories', [
            'sales_case_id' => $salesCase->id,
            'from_status' => 'LANJUT',
            'to_status' => 'MUNDUR',
        ]);
    }

    public function test_withdraw_after_akad_does_not_release_lot(): void
    {
        [$admin, , , $lot] = $this->propertyContext();
        $lot->update(['status' => LotStatus::Reserved]);
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        $salesCase->psjbUtj()->create([
            'psjb_id' => 'PSJB-TEST-001',
            'psjb_date' => today(),
            'process_stage' => 'akad_bast',
            'unit_price' => 0,
            'utj_amount' => 0,
            'dp_all_in' => 0,
            'land_area_m2' => 0,
            'land_price_per_m2' => 0,
        ]);

        $this->actingAs($admin)
            ->patch(route('sales-process.sales-cases.withdraw', $salesCase), ['reason' => 'Pembatalan setelah akad untuk proses legal.'])
            ->assertRedirect();

        $this->assertSame('MUNDUR', $salesCase->fresh()->consumer_status);
        $this->assertSame(LotStatus::Reserved, $lot->fresh()->status);
    }

    public function test_admin_can_update_manual_lead_time_target(): void
    {
        $admin = $this->admin();
        $target = LeadTimeTarget::query()->create([
            'process' => 'psjb',
            'target_description' => 'BI Checking / PSJB (paralel)',
            'target_days' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.lead-times.update', $target), ['target_days' => 14, 'is_active' => true])
            ->assertRedirect();

        $this->assertSame(14, $target->fresh()->target_days);
    }

    /** @return array{User, Branch, Project, Lot} */
    private function propertyContext(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => 'Magelang']);
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'A']);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 1,
            'code' => 'A01',
            'status' => LotStatus::Available,
        ]);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return [$admin, $branch, $project, $lot];
    }

    private function bankWithUnit(string $name, string $unit): Bank
    {
        $bank = Bank::factory()->create(['name' => $name]);
        BankUnit::factory()->for($bank)->create(['name' => $unit]);

        return $bank->load('units');
    }

    private function admin(): User
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return $admin;
    }
}
