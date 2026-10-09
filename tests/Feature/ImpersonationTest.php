<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_as_user_and_return_to_original_session(): void
    {
        [$admin, $target] = $this->users();

        $this->actingAs($admin)
            ->withSession(['active_team_id' => null])
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertSame($target->id, session('impersonated_user_id'));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('impersonator_id'));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'subject_id' => $target->id,
            'event' => 'impersonation_started',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'subject_id' => $target->id,
            'event' => 'impersonation_stopped',
        ]);
    }

    public function test_impersonated_user_cannot_start_another_impersonation(): void
    {
        [$admin, $target] = $this->users();
        $otherUser = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect();

        $this->post(route('admin.users.impersonate', $otherUser))->assertForbidden();
    }

    public function test_super_admin_cannot_impersonate_another_super_admin(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);
        $otherAdmin->globalRoles()->attach($role);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin))
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_start_impersonation(): void
    {
        [$admin, $target] = $this->users();

        $this->actingAs($target)
            ->post(route('admin.users.impersonate', $admin))
            ->assertForbidden();
    }

    /** @return array{User, User} */
    private function users(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->for($branch)->create();
        Rbac::provisionTeam($team);

        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());

        $target = User::factory()->for($branch, 'branch')->create();
        $target->teams()->attach($team, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $target->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());

        return [$admin, $target];
    }
}
