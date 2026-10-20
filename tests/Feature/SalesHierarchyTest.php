<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_sees_only_own_leads_and_agendas(): void
    {
        [$branch, $team] = $this->teamContext();
        $sales = $this->teamUser($branch, $team, 'sales');
        $otherSales = $this->teamUser($branch, $team, 'sales');
        $ownLead = Lead::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'nama_konsumen' => 'Lead milik sales']);
        $otherLead = Lead::factory()->create(['id_lead' => '260627-ON-TI-02', 'branch_id' => $branch->id, 'team_id' => $team->id, 'sales_pic_id' => $otherSales->id, 'created_by' => $otherSales->id, 'nama_konsumen' => 'Lead milik orang lain']);
        $ownAgenda = SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'created_by' => $sales->id, 'title' => 'Agenda milik sales']);
        $otherAgenda = SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'created_by' => $otherSales->id, 'title' => 'Agenda milik orang lain']);

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])->get(route('leads.index'))
            ->assertSee($ownLead->nama_konsumen)->assertDontSee($otherLead->nama_konsumen);
        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])->get(route('sales-agendas.index'))
            ->assertSee($ownAgenda->title)->assertDontSee($otherAgenda->title);
    }

    public function test_koordinator_sees_only_their_team_leads_and_agendas(): void
    {
        [$branch, $project] = $this->branchAndProject();
        $firstTeam = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $secondTeam = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $coordinator = $this->teamUser($branch, $firstTeam, 'koordinator_sales');
        $otherSales = $this->teamUser($branch, $secondTeam, 'sales');
        $firstLead = Lead::factory()->create(['branch_id' => $branch->id, 'team_id' => $firstTeam->id, 'sales_pic_id' => $coordinator->id, 'created_by' => $coordinator->id, 'nama_konsumen' => 'Lead tim pertama']);
        $secondLead = Lead::factory()->create(['id_lead' => '260627-ON-TI-02', 'branch_id' => $branch->id, 'team_id' => $secondTeam->id, 'sales_pic_id' => $otherSales->id, 'created_by' => $otherSales->id, 'nama_konsumen' => 'Lead tim kedua']);
        $firstAgenda = SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $firstTeam->id, 'created_by' => $coordinator->id, 'title' => 'Agenda tim pertama']);
        $secondAgenda = SalesAgenda::factory()->create(['branch_id' => $branch->id, 'team_id' => $secondTeam->id, 'created_by' => $otherSales->id, 'title' => 'Agenda tim kedua']);

        $this->actingAs($coordinator)->withSession(['active_team_id' => $firstTeam->id])->get(route('leads.index'))
            ->assertSee($firstLead->nama_konsumen)->assertDontSee($secondLead->nama_konsumen);
        $this->actingAs($coordinator)->withSession(['active_team_id' => $firstTeam->id])->get(route('sales-agendas.index'))
            ->assertSee($firstAgenda->title)->assertDontSee($secondAgenda->title);
    }

    public function test_bm_and_spv_see_all_leads_in_their_branch_only(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $lead = Lead::factory()->create(['branch_id' => $branch->id, 'nama_konsumen' => 'Lead cabang sendiri']);
        $otherLead = Lead::factory()->create(['id_lead' => '260627-ON-TI-02', 'branch_id' => $otherBranch->id, 'nama_konsumen' => 'Lead cabang lain']);

        foreach (['bm', 'spv'] as $roleName) {
            $user = $this->globalUser($branch, $roleName);
            $this->actingAs($user)->get(route('leads.index'))
                ->assertSee($lead->nama_konsumen)->assertDontSee($otherLead->nama_konsumen);
        }
    }

    public function test_sas_and_sap_can_monitor_branch_leads_and_fee_reports(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->create(['branch_id' => $branch->id]);
        $team = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $coordinator = $this->teamUser($branch, $team, 'koordinator_sales');
        $sales = $this->teamUser($branch, $team, 'sales');
        $lead = Lead::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id, 'nama_konsumen' => 'Lead SAS SAP']);
        $team->update(['coordinator_id' => $coordinator->id]);

        foreach (['sas', 'sap'] as $roleName) {
            $admin = $this->globalUser($branch, $roleName);
            $this->actingAs($admin)->get(route('leads.index'))
                ->assertSee($sales->name)->assertSee($coordinator->name)->assertSee($lead->nama_konsumen);
            $this->actingAs($admin)->get(route('leads.monitoring'))->assertSee($coordinator->name);
            $this->actingAs($admin)->get(route('leads.index'))->assertSeeText('Laporan Fee Sales');
            $this->actingAs($admin)->get(route('sales-fee-reports.index'))->assertOk();
            $this->actingAs($admin)->get(route('sales-fee-reports.print'))->assertOk();
            $this->actingAs($admin)->get(route('sales-fee-reports.export'))->assertDownload();
        }
    }

    public function test_team_cannot_be_linked_to_a_project_in_another_branch(): void
    {
        Rbac::provision();
        $admin = $this->globalUser(null, 'super_admin');
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $project = Project::factory()->create(['branch_id' => $otherBranch->id]);

        $this->actingAs($admin)->post(route('admin.teams.store'), [
            'name' => 'Tim lintas cabang', 'branch_id' => $branch->id, 'project_id' => $project->id, 'is_active' => true,
        ])->assertSessionHasErrors('project_id');
    }

    public function test_project_cannot_have_a_third_active_coordinator_team(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->create(['branch_id' => $branch->id]);
        $admin = $this->globalUser(null, 'super_admin');

        foreach (range(1, 2) as $number) {
            Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id, 'coordinator_id' => User::factory()->for($branch, 'branch')->create()->id]);
        }

        $this->actingAs($admin)->post(route('admin.teams.store'), [
            'name' => 'Tim ketiga', 'branch_id' => $branch->id, 'project_id' => $project->id,
            'coordinator_id' => User::factory()->for($branch, 'branch')->create()->id, 'is_active' => true,
        ])->assertSessionHasErrors('project_id');
    }

    public function test_agenda_team_resolution_handles_zero_one_and_multiple_active_teams(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $sales = User::factory()->for($branch, 'branch')->create();
        $team = Team::factory()->create(['branch_id' => $branch->id]);
        $this->assignTeamRole($sales, $team, 'sales');
        $sales->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'sas')->firstOrFail());

        $this->actingAs($sales)->post(route('sales-agendas.store'), $this->agendaPayload())->assertRedirect();
        $this->assertSame($team->id, SalesAgenda::query()->latest('id')->value('team_id'));

        $sales->teams()->detach($team);
        $this->actingAs($sales)->post(route('sales-agendas.store'), $this->agendaPayload())->assertSessionHasErrors('team_id');

        $secondTeam = Team::factory()->create(['branch_id' => $branch->id]);
        $this->assignTeamRole($sales, $team, 'sales');
        $this->assignTeamRole($sales, $secondTeam, 'koordinator_sales');
        $this->actingAs($sales)->withSession(['active_team_id' => 999999])->post(route('sales-agendas.store'), $this->agendaPayload())->assertSessionHasErrors('team_id');
        $this->actingAs($sales)->withSession(['active_team_id' => $secondTeam->id])->post(route('sales-agendas.store'), $this->agendaPayload())->assertRedirect();

        $coordinator = User::factory()->for($branch, 'branch')->create();
        $this->assignTeamRole($coordinator, $team, 'koordinator_sales');
        $this->actingAs($coordinator)->withSession(['active_team_id' => $team->id])->post(route('sales-agendas.store'), $this->agendaPayload())->assertRedirect();
        $this->assertSame($coordinator->id, SalesAgenda::query()->latest('id')->value('created_by'));
    }

    public function test_branch_cannot_have_two_active_branch_managers_or_supervisors(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $admin = $this->globalUser(null, 'super_admin');
        $firstBm = $this->globalUser($branch, 'bm');
        $secondBm = User::factory()->for($branch, 'branch')->create();
        $bmRole = Role::query()->whereNull('team_id')->where('name', 'bm')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.update', $secondBm), [
            'branch_id' => $branch->id, 'global_role_ids' => [$bmRole->id], 'team_ids' => [], 'team_role_ids' => [],
        ])->assertSessionHasErrors('global_role_ids');
        $this->assertTrue($firstBm->fresh()->hasGlobalRole('bm'));

        $firstSpv = $this->globalUser($branch, 'spv');
        $secondSpv = User::factory()->for($branch, 'branch')->create();
        $spvRole = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.users.update', $secondSpv), [
            'branch_id' => $branch->id, 'global_role_ids' => [$spvRole->id], 'team_ids' => [], 'team_role_ids' => [],
        ])->assertSessionHasErrors('global_role_ids');
        $this->assertTrue($firstSpv->fresh()->hasGlobalRole('spv'));
    }

    public function test_team_coordinator_must_have_the_team_scoped_coordinator_role(): void
    {
        Rbac::provision();
        $admin = $this->globalUser(null, 'super_admin');
        $branch = Branch::factory()->create();
        $project = Project::factory()->create(['branch_id' => $branch->id]);
        $team = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $coordinator = User::factory()->for($branch, 'branch')->create();
        $coordinator->teams()->attach($team, ['is_default' => true]);

        $this->actingAs($admin)->put(route('admin.teams.update', $team), [
            'name' => $team->name, 'branch_id' => $branch->id, 'project_id' => $project->id,
            'coordinator_id' => $coordinator->id, 'is_active' => true,
        ])->assertSessionHasErrors('coordinator_id');

        $this->assignTeamRole($coordinator, $team, 'koordinator_sales');
        $this->actingAs($admin)->put(route('admin.teams.update', $team), [
            'name' => $team->name, 'branch_id' => $branch->id, 'project_id' => $project->id,
            'coordinator_id' => $coordinator->id, 'is_active' => true,
        ])->assertSessionHasNoErrors();
    }

    public function test_team_supervisor_must_be_an_active_supervisor_of_the_same_branch(): void
    {
        Rbac::provision();
        $admin = $this->globalUser(null, 'super_admin');
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $wrongSupervisor = $this->globalUser($otherBranch, 'spv');

        $this->actingAs($admin)->post(route('admin.teams.store'), [
            'name' => 'Tim dengan SPV salah', 'branch_id' => $branch->id, 'supervisor_id' => $wrongSupervisor->id, 'is_active' => true,
        ])->assertSessionHasErrors('supervisor_id');
    }

    /** @return array{0: Branch, 1: Project} */
    private function branchAndProject(): array
    {
        $branch = Branch::factory()->create();

        return [$branch, Project::factory()->create(['branch_id' => $branch->id])];
    }

    /** @return array{0: Branch, 1: Team} */
    private function teamContext(): array
    {
        Rbac::provision();
        [$branch, $project] = $this->branchAndProject();

        return [$branch, Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id])];
    }

    private function globalUser(?Branch $branch, string $roleName): User
    {
        $user = $branch === null ? User::factory()->create() : User::factory()->for($branch, 'branch')->create();
        $role = Role::query()->whereNull('team_id')->where('name', $roleName)->firstOrFail();
        $user->globalRoles()->attach($role);

        return $user;
    }

    private function teamUser(Branch $branch, Team $team, string $roleName): User
    {
        Rbac::provision();
        $user = User::factory()->for($branch, 'branch')->create();
        $this->assignTeamRole($user, $team, $roleName);

        return $user;
    }

    private function assignTeamRole(User $user, Team $team, string $roleName): void
    {
        Rbac::provisionTeam($team);
        $user->teams()->syncWithoutDetaching([$team->id => ['is_default' => true]]);
        setPermissionsTeamId($team->id);
        $user->unsetRelation('roles')->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** @return array<string, string> */
    private function agendaPayload(): array
    {
        return ['agenda_date' => '2026-10-15', 'activity_category' => 'Follow-up', 'title' => 'Agenda hierarchy', 'plan_detail' => 'Follow-up terstruktur untuk Lead.', 'duration_minutes' => 30];
    }
}
