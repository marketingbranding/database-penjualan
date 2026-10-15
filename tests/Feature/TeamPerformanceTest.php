<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesAgendaAssignmentHistory;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use App\TeamPerformanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_roles_can_open_and_export_team_performance(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();

        foreach (['super_admin', 'pusat', 'bm', 'spv', 'sas', 'sap'] as $roleName) {
            $user = $this->globalUser($branch, $roleName);

            $this->actingAs($user)->get(route('team-performance.index'))->assertOk();
            $this->actingAs($user)->get(route('team-performance.export'))->assertDownload();
        }
    }

    public function test_sales_and_koordinator_cannot_open_team_performance(): void
    {
        [$branch, , $team] = $this->reportContext();
        $sales = $this->teamUser($branch, $team, 'sales');
        $coordinator = $this->teamUser($branch, $team, 'koordinator_sales');

        $this->actingAs($sales)->get(route('team-performance.index'))->assertForbidden();
        $this->actingAs($coordinator)->get(route('team-performance.export'))->assertForbidden();
    }

    public function test_metrics_count_status_conversion_overdue_and_agendas(): void
    {
        [$branch, $project, $team, $sales, $viewer] = $this->reportContext();
        $coordinator = User::factory()->for($branch, 'branch')->create();
        $team->update(['coordinator_id' => $coordinator->id]);
        $makeLead = fn (string $id, string $status): Lead => Lead::factory()->create([
            'id_lead' => $id,
            'branch_id' => $branch->id,
            'project_id' => $project->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'tanggal_lead' => '2026-10-10',
            'status_lead' => $status,
        ]);
        $utj = $makeLead('TP-UTJ', 'UTJ');
        $akad = $makeLead('TP-AKAD', 'Akad');
        $overdue = $makeLead('TP-OVERDUE', 'Diskusi');
        $movement = $makeLead('TP-MOVE', 'No Respon');
        LeadActivity::factory()->create(['lead_id' => $movement->id, 'activity_type' => 'Status Update', 'status_to' => 'UTJ', 'happened_at' => '2026-10-10 10:00:00']);
        $overdue->update(['next_follow_up_at' => '2026-10-01 09:00:00']);

        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'owner_id' => $sales->id, 'created_by' => $sales->id, 'agenda_date' => '2026-10-10', 'status' => 'done']);
        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'owner_id' => $sales->id, 'created_by' => $sales->id, 'agenda_date' => '2026-10-01', 'status' => 'planned']);
        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'owner_id' => $sales->id, 'created_by' => $sales->id, 'agenda_date' => '2026-10-10', 'status' => 'cancelled']);

        $report = app(TeamPerformanceService::class)->build($viewer, [
            'branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null,
            'date_from' => '2026-10-01', 'date_to' => '2026-10-31',
        ]);
        $salesMetrics = $report['projects']->first()['teams']->first()['sales']->first();

        $this->assertSame(4, $salesMetrics['lead_total']);
        $this->assertSame(1, $salesMetrics['statuses']['UTJ']);
        $this->assertSame(1, $salesMetrics['statuses']['Akad']);
        $this->assertSame(1, $salesMetrics['movement_utj']);
        $this->assertSame(1, $salesMetrics['cohort_reached_utj']);
        $this->assertSame(1, $salesMetrics['cohort_reached_akad']);
        $this->assertSame(1, $salesMetrics['overdue_followups']);
        $this->assertSame(1, $salesMetrics['agendas_done']);
        $this->assertSame(1, $salesMetrics['agendas_cancelled']);
        $this->assertSame(1, $salesMetrics['agendas_overdue']);
        $this->assertSame(50.0, $salesMetrics['completion_rate']);
    }

    public function test_scope_filters_project_coordinator_and_branch(): void
    {
        [$branch, $project, $team, $sales, $viewer] = $this->reportContext();
        $otherBranch = Branch::factory()->create();
        $coordinator = User::factory()->for($branch, 'branch')->create();
        $team->update(['coordinator_id' => $coordinator->id]);
        $otherProject = Project::factory()->create(['branch_id' => $otherBranch->id]);
        $otherTeam = Team::factory()->create(['branch_id' => $otherBranch->id, 'project_id' => $otherProject->id]);
        $otherSales = $this->teamUser($otherBranch, $otherTeam, 'sales');
        Lead::factory()->create(['id_lead' => 'TP-LOCAL', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'tanggal_lead' => '2026-10-10']);
        Lead::factory()->create(['id_lead' => 'TP-FOREIGN', 'branch_id' => $otherBranch->id, 'project_id' => $otherProject->id, 'team_id' => $otherTeam->id, 'sales_pic_id' => $otherSales->id, 'created_by' => $otherSales->id, 'tanggal_lead' => '2026-10-10']);

        $report = app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);

        $this->assertCount(1, $report['projects']);
        $this->assertSame($project->id, $report['projects']->first()['project_id']);
        $this->assertSame(1, $report['totals']['lead_total']);

        $filtered = app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => $coordinator->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $this->assertSame(1, $filtered['totals']['lead_total']);
    }

    public function test_redirects_are_counted_for_new_and_previous_sales_owner(): void
    {
        [$branch, $project, $team, $sales, $viewer] = $this->reportContext();
        $newOwner = $this->teamUser($branch, $team, 'sales');
        $agenda = SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'owner_id' => $newOwner->id, 'created_by' => $sales->id, 'agenda_date' => '2026-10-10']);
        SalesAgendaAssignmentHistory::factory()->create(['sales_agenda_id' => $agenda->id, 'action' => 'redirected', 'from_user_id' => $sales->id, 'to_user_id' => $newOwner->id, 'by_user_id' => $viewer->id, 'created_at' => '2026-10-10 10:00:00']);

        $report = app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $salesRows = $report['projects']->first()['teams']->first()['sales'];

        $this->assertSame(1, $salesRows->firstWhere('sales_id', $sales->id)['redirected_out']);
        $this->assertSame(1, $salesRows->firstWhere('sales_id', $newOwner->id)['redirected_in']);
    }

    public function test_two_coordinator_teams_on_one_project_remain_separate(): void
    {
        [$branch, $project] = $this->branchAndProject();
        $teamOne = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $teamTwo = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $salesOne = $this->teamUser($branch, $teamOne, 'sales');
        $salesTwo = $this->teamUser($branch, $teamTwo, 'sales');
        $viewer = $this->globalUser($branch, 'sas');
        Lead::factory()->create(['id_lead' => 'TP-ONE', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $teamOne->id, 'sales_pic_id' => $salesOne->id, 'created_by' => $salesOne->id, 'tanggal_lead' => '2026-10-10']);
        Lead::factory()->create(['id_lead' => 'TP-TWO', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $teamTwo->id, 'sales_pic_id' => $salesTwo->id, 'created_by' => $salesTwo->id, 'tanggal_lead' => '2026-10-10']);

        $report = app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);

        $this->assertCount(2, $report['projects']->first()['teams']);
        $this->assertSame(1, $report['projects']->first()['teams']->first()['totals']['lead_total']);
        $this->assertSame(1, $report['projects']->first()['teams']->last()['totals']['lead_total']);
    }

    public function test_jakarta_midnight_boundaries_are_used_for_overdue_followups(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 17:30:00', 'UTC'));
        [$branch, $project, $team, $sales, $viewer] = $this->reportContext();
        Lead::factory()->create(['id_lead' => 'TP-MIDNIGHT-1', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'next_follow_up_at' => '2026-10-14 17:30:00']);
        Lead::factory()->create(['id_lead' => 'TP-MIDNIGHT-2', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'next_follow_up_at' => '2026-10-14 16:30:00']);
        $boundaryLead = Lead::factory()->create(['id_lead' => 'TP-MIDNIGHT-3', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'status_lead' => 'No Respon']);
        $previousLead = Lead::factory()->create(['id_lead' => 'TP-MIDNIGHT-4', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'status_lead' => 'No Respon']);
        LeadActivity::factory()->create(['lead_id' => $boundaryLead->id, 'activity_type' => 'Status Update', 'status_to' => 'UTJ', 'happened_at' => '2026-10-14 17:30:00']);
        LeadActivity::factory()->create(['lead_id' => $previousLead->id, 'activity_type' => 'Status Update', 'status_to' => 'UTJ', 'happened_at' => '2026-10-14 16:30:00']);

        $report = app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-15', 'date_to' => '2026-10-15']);

        $this->assertSame(1, $report['projects']->first()['teams']->first()['sales']->first()['overdue_followups']);
        $this->assertSame(1, $report['projects']->first()['teams']->first()['sales']->first()['movement_utj']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 16:30:00', 'UTC'));
        $report = app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-15', 'date_to' => '2026-10-15']);
        $this->assertSame(0, $report['projects']->first()['teams']->first()['sales']->first()['overdue_followups']);
        CarbonImmutable::setTestNow();
    }

    public function test_query_count_does_not_grow_with_sales_count(): void
    {
        [$branch, $project, $team, $firstSales, $viewer] = $this->reportContext();
        Lead::factory()->create(['id_lead' => 'TP-Q-BASE', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $firstSales->id, 'created_by' => $firstSales->id, 'tanggal_lead' => '2026-10-10']);
        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'owner_id' => $firstSales->id, 'created_by' => $firstSales->id, 'agenda_date' => '2026-10-10']);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(1, 500) as $number) {
            Lead::factory()->create(['id_lead' => 'TP-Q-'.$number, 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $firstSales->id, 'created_by' => $firstSales->id, 'tanggal_lead' => '2026-10-10']);
            SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'owner_id' => $firstSales->id, 'created_by' => $firstSales->id, 'agenda_date' => '2026-10-10']);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TeamPerformanceService::class)->build($viewer, ['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => null, 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']);
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($smallCount + 2, $largeCount);
    }

    public function test_agenda_owner_drilldown_does_not_widen_scope(): void
    {
        [$branch, $project, $team, $sales, $viewer] = $this->reportContext();
        $foreignBranch = Branch::factory()->create();
        $foreignTeam = Team::factory()->create(['branch_id' => $foreignBranch->id, 'project_id' => Project::factory()->create(['branch_id' => $foreignBranch->id])->id]);
        $foreignSales = $this->teamUser($foreignBranch, $foreignTeam, 'sales');
        $foreignAgenda = SalesAgenda::factory()->create(['branch_id' => $foreignBranch->id, 'team_id' => $foreignTeam->id, 'owner_id' => $foreignSales->id, 'created_by' => $foreignSales->id, 'title' => 'Agenda luar scope']);

        $this->actingAs($viewer)->get(route('sales-agendas.index', ['owner_id' => $foreignSales->id]))
            ->assertOk()
            ->assertDontSee($foreignAgenda->title);
    }

    /** @return array{0: Branch, 1: Project, 2: Team, 3: User, 4: User} */
    private function reportContext(): array
    {
        Rbac::provision();
        [$branch, $project] = $this->branchAndProject();
        $team = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $sales = $this->teamUser($branch, $team, 'sales');
        $viewer = $this->globalUser($branch, 'sas');

        return [$branch, $project, $team, $sales, $viewer];
    }

    /** @return array{0: Branch, 1: Project} */
    private function branchAndProject(): array
    {
        $branch = Branch::factory()->create();

        return [$branch, Project::factory()->create(['branch_id' => $branch->id])];
    }

    /** @return array{0: Branch, 1: Project, 2: Team} */
    private function teamContext(): array
    {
        [$branch, $project] = $this->branchAndProject();

        return [$branch, $project, Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id])];
    }

    private function globalUser(Branch $branch, string $roleName): User
    {
        $user = User::factory()->for($branch, 'branch')->create();
        $user->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function teamUser(Branch $branch, Team $team, string $roleName): User
    {
        Rbac::provision();
        Rbac::provisionTeam($team);
        $user = User::factory()->for($branch, 'branch')->create();
        $user->teams()->attach($team, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $user->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }
}
