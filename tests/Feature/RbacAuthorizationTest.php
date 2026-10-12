<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_pages(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_open_admin_pages(): void
    {
        Rbac::provision();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_global_role_applies_without_a_team_membership(): void
    {
        Rbac::provision();
        $user = User::factory()->create();
        $adminRole = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $user->globalRoles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('admin.teams.index'))
            ->assertOk();
    }

    public function test_pusat_role_can_access_central_sales_process_data(): void
    {
        Rbac::provision();
        $pusat = User::factory()->create();
        $pusatRole = Role::query()->whereNull('team_id')->where('name', 'pusat')->firstOrFail();
        $pusat->globalRoles()->attach($pusatRole);

        $this->actingAs($pusat)
            ->get(route('sales-process.bank'))
            ->assertOk();

        $this->assertTrue($pusat->fresh()->canAccessAllBranches());
    }

    public function test_sap_role_is_limited_to_filing(): void
    {
        Rbac::provision();
        $sap = User::factory()->create();
        $sapRole = Role::query()->whereNull('team_id')->where('name', 'sap')->firstOrFail();
        $sap->globalRoles()->attach($sapRole);

        $this->actingAs($sap)
            ->get(route('sales-process.pemberkasan'))
            ->assertOk();

        $this->actingAs($sap)
            ->get(route('sales-process.bank'))
            ->assertForbidden();
    }

    public function test_same_user_can_have_different_roles_in_different_teams(): void
    {
        Rbac::provision();
        $salesTeam = Team::factory()->create();
        $supervisorTeam = Team::factory()->create();
        Rbac::provisionTeam($salesTeam);
        Rbac::provisionTeam($supervisorTeam);

        $user = User::factory()->create();
        $user->teams()->attach([$salesTeam->id, $supervisorTeam->id]);

        setPermissionsTeamId($salesTeam->id);
        $user->assignRole(Role::query()->where('team_id', $salesTeam->id)->where('name', 'sales')->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue(Gate::forUser($user)->allows('sales.view-own'));
        $this->assertFalse(Gate::forUser($user)->allows('reports.view'));

        setPermissionsTeamId($supervisorTeam->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole(Role::query()->where('team_id', $supervisorTeam->id)->where('name', 'koordinator_sales')->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue(Gate::forUser($user)->allows('sales.view-team'));
        $this->assertFalse(Gate::forUser($user)->allows('reports.view'));
    }

    public function test_team_permission_only_applies_in_its_team_context(): void
    {
        Rbac::provision();
        $authorizedTeam = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        Rbac::provisionTeam($authorizedTeam);
        Rbac::provisionTeam($otherTeam);

        $user = User::factory()->create();
        $user->teams()->attach([$authorizedTeam->id, $otherTeam->id]);
        setPermissionsTeamId($authorizedTeam->id);
        $user->assignRole(Role::query()->where('team_id', $authorizedTeam->id)->where('name', 'sales')->firstOrFail());

        $this->actingAs($user)
            ->withSession(['active_team_id' => $authorizedTeam->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['active_team_id' => $otherTeam->id])
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        Rbac::provision();
        $superAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdmin->globalRoles()->attach($role);

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $superAdmin), [
                'team_ids' => [],
                'global_role_ids' => [],
                'team_role_ids' => [],
            ])
            ->assertSessionHasErrors('global_role_ids');

        $this->assertTrue($superAdmin->fresh()->hasGlobalRole('super_admin'));
    }

    public function test_super_admin_can_render_access_management_pages(): void
    {
        Rbac::provision();
        $team = Team::factory()->create();
        Rbac::provisionTeam($team);
        $superAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdmin->globalRoles()->attach($role);
        $superAdmin->teams()->attach($team, ['is_default' => true]);

        $this->actingAs($superAdmin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.users.edit', $superAdmin))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.teams.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.teams.edit', $team))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.roles.edit', $role))->assertOk();
    }

    public function test_branch_role_requires_exactly_one_branch_assignment(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $superAdmin = User::factory()->create();
        $target = User::factory()->create();
        $superAdminRole = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $spvRole = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();
        $superAdmin->globalRoles()->attach($superAdminRole);

        $payload = [
            'team_ids' => [],
            'global_role_ids' => [$spvRole->id],
            'team_role_ids' => [],
        ];

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $target), $payload)
            ->assertSessionHasErrors('branch_id');

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $target), [...$payload, 'branch_id' => $branch->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($branch->id, $target->fresh()->branch_id);
    }

    public function test_reports_to_must_be_in_the_same_branch(): void
    {
        Rbac::provision();
        $magelang = Branch::factory()->create(['name' => 'Magelang']);
        $semarang = Branch::factory()->create(['name' => 'Semarang']);
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $spvRole = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();
        $superAdmin->globalRoles()->attach($superAdminRole);

        $bm = User::factory()->for($semarang)->create();
        $target = User::factory()->for($semarang)->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.users.update', $target), [
                'branch_id' => $magelang->id,
                'reports_to_id' => $bm->id,
                'global_role_ids' => [$spvRole->id],
                'team_ids' => [],
                'team_role_ids' => [],
            ])
            ->assertSessionHasErrors('reports_to_id');

        $this->assertNull($target->fresh()->reports_to_id);
    }
}
