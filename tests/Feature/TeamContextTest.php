<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_switch_active_team(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('team-context.update', $team))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_team_id', $team->id);
    }

    public function test_admin_can_create_team_with_an_automatic_code(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        $this->actingAs($admin)
            ->post(route('admin.teams.store'), [
                'name' => 'Sales Magelang',
                'parent_id' => null,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame('TEAM-001', Team::query()->firstOrFail()->code);
    }

    public function test_user_cannot_switch_to_another_users_team(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('team-context.update', $team))
            ->assertForbidden();
    }

    public function test_super_admin_can_switch_to_any_active_team(): void
    {
        Rbac::provision();
        $team = Team::factory()->create();
        $superAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdmin->globalRoles()->attach($role);

        $this->actingAs($superAdmin)
            ->post(route('team-context.update', $team))
            ->assertRedirect()
            ->assertSessionHas('active_team_id', $team->id);
    }

    public function test_inactive_team_cannot_be_selected(): void
    {
        $team = Team::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $this->actingAs($user)
            ->post(route('team-context.update', $team))
            ->assertNotFound();
    }
}
