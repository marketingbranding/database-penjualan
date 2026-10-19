<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesAgendaEvidence;
use App\Models\SalesCase;
use App\Models\Team;
use App\Models\User;
use App\PaymentMethod;
use App\Services\CoordinatorSalesDashboardService;
use App\Support\BusinessTime;
use App\Support\Rbac;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_admin_dashboard_only_counts_assigned_branch(): void
    {
        Rbac::provision();
        [$branch, $project] = $this->projectContext('Magelang');
        [, $otherProject] = $this->projectContext('Semarang');
        $this->createSale($project, 'A01', PaymentMethod::Kpr);
        $this->createSale($otherProject, 'B01', PaymentMethod::Cash);
        Lot::factory()->for($project)->for(ProjectBlock::factory()->for($project), 'block')->create([
            'code' => 'C01', 'number' => 1, 'status' => LotStatus::Available,
        ]);

        $admin = User::factory()->for($branch)->create();
        $adminRole = Role::query()->whereNull('team_id')->where('name', 'bm')->firstOrFail();
        $admin->globalRoles()->attach($adminRole);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['consumers'] === 1
                && $metrics['kpr'] === 1
                && $metrics['cash'] === 0
                && $metrics['availableLots'] === 1);
    }

    public function test_super_admin_can_filter_dashboard_by_branch(): void
    {
        Rbac::provision();
        [$branch, $project] = $this->projectContext('Magelang');
        [, $otherProject] = $this->projectContext('Semarang');
        $this->createSale($project, 'A01', PaymentMethod::Kpr);
        $this->createSale($otherProject, 'B01', PaymentMethod::Cash);

        $superAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdmin->globalRoles()->attach($role);

        $this->actingAs($superAdmin)
            ->get(route('dashboard', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['consumers'] === 1
                && $metrics['kpr'] === 1
                && $metrics['cash'] === 0);
    }

    public function test_personal_sales_dashboard_shows_only_own_data_and_normalizes_phone(): void
    {
        [$branch, $project, $team, $sales] = $this->personalSalesContext();
        $today = BusinessTime::now()->toDateString();
        $otherSales = User::factory()->for($branch, 'branch')->create();
        $ownLead = Lead::factory()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'project_id' => $project->id,
            'sales_pic_id' => $sales->id,
            'nama_konsumen' => 'Milik Sales',
            'no_hp' => '081234567890',
            'status_lead' => 'Diskusi',
        ]);
        Lead::factory()->create([
            'id_lead' => '260627-ON-TI-02',
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'project_id' => $project->id,
            'sales_pic_id' => $otherSales->id,
            'nama_konsumen' => 'Milik Sales Lain',
            'status_lead' => 'Diskusi',
        ]);
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'created_by' => $sales->id,
            'owner_id' => $sales->id,
            'lead_id' => $ownLead->id,
            'agenda_date' => $today,
            'title' => 'Hubungi Milik Sales',
        ]);

        $response = $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertViewIs('dashboard-personal')
            ->assertSee('Milik Sales')
            ->assertDontSee('Milik Sales Lain')
            ->assertSee('https://wa.me/6281234567890')
            ->assertDontSee('Target akad:');
        $response->assertViewHas('nextAgenda', fn (array $nextAgenda): bool => $nextAgenda['model']->is($agenda));
    }

    public function test_personal_action_counts_use_jakarta_day_boundaries(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 17:00:00', 'UTC'));
        [$branch, $project, $team, $sales] = $this->personalSalesContext();

        $overdue = Lead::factory()->create(['id_lead' => '260627-ON-TI-02', 'branch_id' => $branch->id, 'team_id' => $team->id, 'project_id' => $project->id, 'sales_pic_id' => $sales->id, 'next_follow_up_at' => '2026-10-15 16:59:00']);
        Lead::factory()->create(['id_lead' => '260627-ON-TI-03', 'branch_id' => $branch->id, 'team_id' => $team->id, 'project_id' => $project->id, 'sales_pic_id' => $sales->id, 'next_follow_up_at' => '2026-10-15 17:30:00']);
        $newLead = Lead::factory()->create(['id_lead' => '260627-ON-TI-04', 'branch_id' => $branch->id, 'team_id' => $team->id, 'project_id' => $project->id, 'sales_pic_id' => $sales->id, 'status_lead' => 'No Respon', 'tanggal_lead' => '2026-10-15']);
        LeadActivity::factory()->create(['lead_id' => $newLead->id, 'activity_type' => 'Lead Baru', 'status_to' => 'No Respon']);
        $oldNewLead = Lead::factory()->create(['id_lead' => '260627-ON-TI-05', 'branch_id' => $branch->id, 'team_id' => $team->id, 'project_id' => $project->id, 'sales_pic_id' => $sales->id, 'status_lead' => 'No Respon', 'tanggal_lead' => '2026-10-01']);
        LeadActivity::factory()->create(['lead_id' => $oldNewLead->id, 'activity_type' => 'Lead Baru', 'status_to' => 'No Respon']);

        $response = $this->actingAs($sales)->withSession(['active_team_id' => $team->id])->get(route('dashboard'));

        $response->assertViewHas('actions', fn (array $actions): bool => $actions['overdue'] === 1 && $actions['today'] === 1 && $actions['new'] === 1);
        CarbonImmutable::setTestNow();
    }

    public function test_personal_and_other_dashboard_quick_actions_are_role_specific(): void
    {
        [$branch, , $team, $sales] = $this->personalSalesContext();

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])->get(route('dashboard'))
            ->assertSee('Catat follow-up')
            ->assertSee('Agenda baru')
            ->assertDontSee('Konsumen baru');

        $manager = User::factory()->for($branch, 'branch')->create();
        $manager->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertViewIs('dashboard')
            ->assertSee('Konsumen baru')
            ->assertSee('PSJB / UTJ')
            ->assertDontSee('Catat follow-up');
    }

    public function test_coordinator_dashboard_is_scoped_and_sorted_for_the_active_team(): void
    {
        [$branch, $project, $team, $coordinator, $salesOne, $salesTwo, $otherTeam, $otherSales] = $this->coordinatorDashboardContext();
        $today = BusinessTime::now()->toDateString();
        $this->assertTrue(app(CoordinatorSalesDashboardService::class)->isCoordinator($coordinator));
        Lead::factory()->create(['id_lead' => 'COORD-ONE', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $salesOne->id, 'created_by' => $salesOne->id, 'nama_konsumen' => 'Lead tim saya', 'next_follow_up_at' => '2026-10-15 16:00:00']);
        Lead::factory()->create(['id_lead' => 'COORD-TWO', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $salesTwo->id, 'created_by' => $salesTwo->id, 'nama_konsumen' => 'Lead sales dua']);
        Lead::factory()->create(['id_lead' => 'COORD-OTHER', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $otherTeam->id, 'sales_pic_id' => $otherSales->id, 'created_by' => $otherSales->id, 'nama_konsumen' => 'Lead tim lain']);
        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'created_by' => $salesTwo->id, 'owner_id' => $salesTwo->id, 'agenda_date' => $today, 'status' => 'planned', 'title' => 'Agenda belum selesai']);
        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'created_by' => $salesOne->id, 'owner_id' => $salesOne->id, 'agenda_date' => $today, 'status' => 'done', 'title' => 'Agenda selesai']);

        $response = $this->actingAs($coordinator)->withSession(['active_team_id' => $team->id])->get(route('dashboard'));

        $response->assertViewIs('dashboard-coordinator')->assertViewHas('salesRows', function (array $rows) use ($salesOne): bool {
            return $rows[0]['user']->is($salesOne) && $rows[0]['overdue'] === 1;
        });
        $response->assertViewHas('metrics', fn (array $metrics): bool => $metrics['overdue'] === 1 && $metrics['agendaDone'] === 1 && $metrics['agendaTotal'] === 2);
    }

    public function test_coordinator_dashboard_switches_between_multiple_active_teams(): void
    {
        [$branch, $project, $team, $coordinator, , , $otherTeam, $otherSales] = $this->coordinatorDashboardContext(true);
        Lead::factory()->create(['id_lead' => 'COORD-SWITCH', 'branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $otherTeam->id, 'sales_pic_id' => $otherSales->id, 'created_by' => $otherSales->id, 'nama_konsumen' => 'Lead team switched']);

        $response = $this->actingAs($coordinator)->withSession(['active_team_id' => $otherTeam->id])->get(route('dashboard'));

        $response->assertViewIs('dashboard-coordinator')->assertViewHas('team', fn (Team $team): bool => $team->is($otherTeam));
    }

    public function test_coordinator_hides_redirect_for_done_and_evidence_agendas(): void
    {
        [$branch, , $team, $coordinator, $salesOne] = $this->coordinatorDashboardContext();
        $today = BusinessTime::now()->toDateString();
        SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'created_by' => $salesOne->id, 'owner_id' => $salesOne->id, 'agenda_date' => $today, 'status' => 'done', 'title' => 'Done agenda']);
        $withEvidence = SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'created_by' => $salesOne->id, 'owner_id' => $salesOne->id, 'agenda_date' => $today, 'status' => 'planned', 'title' => 'Evidence agenda']);
        SalesAgendaEvidence::factory()->create(['sales_agenda_id' => $withEvidence->id]);

        $this->actingAs($coordinator)->withSession(['active_team_id' => $team->id])->get(route('dashboard'))
            ->assertViewHas('teamAgendas', fn (array $agendas): bool => collect($agendas)->every(fn (array $agenda): bool => $agenda['isRedirectable'] === false));
    }

    /** @return array{Branch, Project} */
    private function projectContext(string $branchName): array
    {
        $branch = Branch::factory()->create(['name' => $branchName]);
        $project = Project::factory()->for($branch)->create(['name' => $branchName.' Residence']);

        return [$branch, $project];
    }

    private function createSale(Project $project, string $code, PaymentMethod $method): SalesCase
    {
        $block = ProjectBlock::factory()->for($project)->create(['code' => $code[0]]);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'code' => $code,
            'number' => (int) substr($code, 1),
            'status' => LotStatus::Reserved,
        ]);

        return SalesCase::factory()
            ->for(Consumer::factory())
            ->for($lot)
            ->create(['payment_method' => $method]);
    }

    /** @return array{Branch, Project, Team, User} */
    private function personalSalesContext(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->for($branch)->create();
        $team = Team::factory()->for($branch)->for($project)->create();
        Rbac::provisionTeam($team);
        $sales = User::factory()->for($branch, 'branch')->create();
        $team->users()->attach($sales, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $sales->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());

        return [$branch, $project, $team, $sales];
    }

    /** @return array{Branch, Project, Team, User, User, User, Team, User} */
    private function coordinatorDashboardContext(bool $multipleTeams = false): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->for($branch)->create();
        $team = Team::factory()->for($branch)->for($project)->create(['coordinator_id' => null]);
        $otherTeam = Team::factory()->for($branch)->for($project)->create(['coordinator_id' => null]);
        Rbac::provisionTeam($team);
        Rbac::provisionTeam($otherTeam);
        $coordinator = User::factory()->for($branch, 'branch')->create();
        $salesOne = User::factory()->for($branch, 'branch')->create(['name' => 'Sales Satu']);
        $salesTwo = User::factory()->for($branch, 'branch')->create(['name' => 'Sales Dua']);
        $otherSales = User::factory()->for($branch, 'branch')->create(['name' => 'Sales Team Lain']);
        $team->users()->attach([$coordinator->id => ['is_default' => true], $salesOne->id => ['is_default' => true], $salesTwo->id => ['is_default' => false]]);
        $otherTeam->users()->attach($otherSales);
        $team->update(['coordinator_id' => $coordinator->id]);
        $otherTeam->update(['coordinator_id' => $multipleTeams ? $coordinator->id : null]);
        setPermissionsTeamId($team->id);
        $coordinator->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'koordinator_sales')->firstOrFail());
        $salesOne->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());
        $salesTwo->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());
        $otherSales->assignRole(Role::query()->where('team_id', $otherTeam->id)->where('name', 'sales')->firstOrFail());

        if ($multipleTeams) {
            $otherTeam->users()->attach($coordinator, ['is_default' => false]);
            setPermissionsTeamId($otherTeam->id);
            $coordinator->assignRole(Role::query()->where('team_id', $otherTeam->id)->where('name', 'koordinator_sales')->firstOrFail());
            setPermissionsTeamId($team->id);
        }

        return [$branch, $project, $team, $coordinator, $salesOne, $salesTwo, $otherTeam, $otherSales];
    }
}
