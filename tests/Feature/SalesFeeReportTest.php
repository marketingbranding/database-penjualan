<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFeeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_role_can_view_aggregated_report_and_print(): void
    {
        [$branch, $team, $viewer, $sales, $project] = $this->reportContext();
        $lead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'project_id' => $project->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'tanggal_lead' => '2026-10-05',
            'status_lead' => 'UTJ',
        ]);
        LeadActivity::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $sales->id,
            'status_to' => 'UTJ',
            'happened_at' => '2026-10-05 10:00',
        ]);
        SalesAgenda::factory()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'created_by' => $sales->id,
            'lead_id' => $lead->id,
            'agenda_date' => '2026-10-05',
            'status' => 'done',
        ]);

        $this->actingAs($viewer)
            ->get(route('sales-fee-reports.index', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee($sales->name)
            ->assertSee($project->name)
            ->assertSee('Total Lead');

        $this->actingAs($viewer)
            ->get(route('sales-fee-reports.print', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('LAPORAN PERFORMA SALES')
            ->assertSee($lead->id_lead);

        $this->actingAs($viewer)
            ->get(route('sales-fee-reports.export', [
                'date_from' => '2026-10-01',
                'date_to' => '2026-10-31',
            ]))
            ->assertDownload('laporan-performa-sales-2026-10-01-sd-2026-10-31.xlsx');
    }

    public function test_sales_cannot_access_sales_fee_report(): void
    {
        [, $team, , $sales] = $this->reportContext();

        setPermissionsTeamId($team->id);

        $this->actingAs($sales)
            ->get(route('sales-fee-reports.index'))
            ->assertForbidden();
    }

    /** @return array{Branch, Team, User, User, Project} */
    private function reportContext(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->for($branch)->create();
        Rbac::provisionTeam($team);
        $project = Project::factory()->for($branch)->create();

        $viewer = User::factory()->for($branch, 'branch')->create();
        $viewer->teams()->attach($team, ['is_default' => true]);
        $viewer->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'sas')->firstOrFail());

        $sales = User::factory()->for($branch, 'branch')->create();
        $team->users()->attach($sales, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $sales->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());

        return [$branch, $team, $viewer, $sales, $project];
    }
}
