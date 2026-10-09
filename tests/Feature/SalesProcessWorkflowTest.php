<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankUnit;
use App\Models\Branch;
use App\Models\LeadTimeTarget;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\PsjbUtjCase;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesProcessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_progress_a_sales_case_from_filing_to_warranty(): void
    {
        [$admin, $salesCase, $bank, $unit] = $this->context();
        $this->seedTargets();

        $this->actingAs($admin)->post(route('sales-process.pemberkasan.store'), [
            'sales_case_id' => $salesCase->id,
            'started_at' => '2026-10-09',
            'status' => 'submitted',
            'bank_id' => $bank->id,
            'bank_unit_id' => $unit->id,
            'requested_amount' => 160000000,
            'requested_tenor' => '20 Tahun',
        ])->assertRedirect();

        $filing = $salesCase->filings()->firstOrFail();
        $this->assertSame('BRK-'.$salesCase->id.'-1', $filing->filing_id);

        $this->actingAs($admin)->post(route('sales-process.bank.store'), [
            'sales_case_id' => $salesCase->id,
            'sales_case_filing_id' => $filing->id,
            'bank_id' => $bank->id,
            'bank_unit_id' => $unit->id,
            'submitted_at' => '2026-10-10',
            'result' => 'APPROVE',
            'status' => 'proses',
            'approved_amount' => 160000000,
            'approved_tenor' => '20 Tahun',
        ])->assertRedirect();

        $bankProcess = $salesCase->bankProcesses()->firstOrFail();
        $this->assertSame('approved', $bankProcess->status);
        $this->assertSame('SP3K-'.$salesCase->id.'-1', $bankProcess->sp3k_number);

        $this->actingAs($admin)->post(route('sales-process.ppjb-developer.store'), [
            'sales_case_id' => $salesCase->id,
            'bank_process_id' => $bankProcess->id,
            'started_at' => '2026-10-11',
            'sp3k_date' => '2026-10-10',
            'signed_at' => '2026-10-12',
            'status' => 'signed',
        ])->assertRedirect();

        $ppjb = $salesCase->ppjbDeveloper()->firstOrFail();
        $this->actingAs($admin)->post(route('sales-process.akad.store'), [
            'sales_case_id' => $salesCase->id,
            'ppjb_developer_case_id' => $ppjb->id,
            'started_at' => '2026-10-13',
            'ppjb_signed_at' => '2026-10-12',
            'akad_date' => '2026-10-14',
            'status' => 'completed',
            'building_progress' => 100,
        ])->assertRedirect();

        $akad = $salesCase->akad()->firstOrFail();
        $this->actingAs($admin)->post(route('sales-process.bast.store'), [
            'sales_case_id' => $salesCase->id,
            'akad_case_id' => $akad->id,
            'started_at' => '2026-10-15',
            'akad_date' => '2026-10-14',
            'ready_100_at' => '2026-10-15',
            'bast_date' => '2026-10-16',
            'status' => 'completed',
            'building_progress' => 100,
        ])->assertRedirect();

        $bast = $salesCase->bast()->firstOrFail();
        $this->actingAs($admin)->post(route('warranties.store'), [
            'sales_case_id' => $salesCase->id,
            'bast_case_id' => $bast->id,
            'status' => 'proses',
            'reported_at' => '2026-10-17',
            'detail' => 'Pintu perlu penyesuaian.',
        ])->assertRedirect();

        $this->assertDatabaseHas('warranties', [
            'sales_case_id' => $salesCase->id,
            'bast_case_id' => $bast->id,
            'status' => 'proses',
        ]);
        $this->assertSame('bast', $salesCase->psjbUtj()->value('process_stage'));

        $this->actingAs($admin)
            ->get(route('consumers.show', $salesCase))
            ->assertOk()
            ->assertSee('Timeline proses')
            ->assertSee('Pemberkasan')
            ->assertSee('Garansi');
    }

    public function test_branch_user_cannot_open_a_sales_case_from_another_branch(): void
    {
        [$admin, $salesCase] = $this->context();
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->for($otherBranch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();
        $user->globalRoles()->attach($role);

        $response = $this->actingAs($user)->post(route('sales-process.pemberkasan.store'), [
            'sales_case_id' => $salesCase->id,
            'started_at' => '2026-10-09',
            'status' => 'input',
        ]);

        $response->assertForbidden();
    }

    /** @return array{User, SalesCase, Bank, BankUnit} */
    private function context(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => 'Magelang']);
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'A']);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create(['code' => 'A01']);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);
        $salesCase = SalesCase::factory()->for($lot)->for($admin, 'creator')->create();
        PsjbUtjCase::factory()->for($salesCase)->create();
        $bank = Bank::factory()->create(['name' => 'BTN']);
        $unit = BankUnit::factory()->for($bank)->create(['name' => 'Magelang']);

        return [$admin, $salesCase, $bank, $unit];
    }

    private function seedTargets(): void
    {
        foreach ([['pemberkasan', 5], ['proses_bank', 10], ['ppjb_dev', 10], ['akad', 3], ['bast', 1]] as [$process, $days]) {
            LeadTimeTarget::query()->create([
                'process' => $process,
                'target_description' => $process,
                'target_days' => $days,
                'is_active' => true,
            ]);
        }
    }
}
