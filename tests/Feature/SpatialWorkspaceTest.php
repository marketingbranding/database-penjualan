<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatialWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_render_the_spatial_workspace(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => 'Magelang']);
        $team = Team::factory()->for($branch)->create(['name' => 'Team Alpha']);
        $admin = User::factory()->for($branch)->create(['name' => 'Admin Workspace']);
        $member = User::factory()->for($branch)->create(['name' => 'Sales Alpha']);
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'bm')->firstOrFail());
        $member->teams()->attach($team);

        $this->actingAs($admin)
            ->get(route('admin.spatial-workspace'))
            ->assertOk()
            ->assertSeeText('Spatial workspace')
            ->assertSee('data-spatial-svg', false)
            ->assertSeeText('Mode lihat')
            ->assertViewHas('workspace', function (array $workspace) use ($branch, $team, $member): bool {
                return count($workspace['branches']) === 1
                    && $workspace['branches'][0]['id'] === $branch->id
                    && $workspace['branches'][0]['teams'][0]['id'] === $team->id
                    && in_array($member->id, $workspace['branches'][0]['teams'][0]['userIds'], true)
                    && collect($workspace['users'])->contains('id', $member->id);
            });
    }

    public function test_user_without_workspace_permission_is_forbidden(): void
    {
        Rbac::provision();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.spatial-workspace'))
            ->assertForbidden();
    }

    public function test_branch_scoped_user_only_receives_their_branch(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => 'Magelang']);
        $otherBranch = Branch::factory()->create(['name' => 'Semarang']);
        Team::factory()->for($branch)->create(['name' => 'Team Magelang']);
        Team::factory()->for($otherBranch)->create(['name' => 'Team Semarang']);
        $manager = User::factory()->for($branch)->create();
        $manager->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'bm')->firstOrFail());

        $this->actingAs($manager)
            ->get(route('admin.spatial-workspace'))
            ->assertViewHas('workspace.branches', fn (array $branches): bool => count($branches) === 1
                && $branches[0]['id'] === $branch->id);
    }

    public function test_super_admin_can_assign_user_to_a_team_and_audit_the_change(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->for($branch)->create();
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());

        $this->actingAs($admin)
            ->patchJson(route('admin.spatial-workspace.users.update', $target), [
                'team_id' => $team->id,
                'reports_to_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('user.teamId', $team->id);

        $this->assertSame($branch->id, $target->fresh()->branch_id);
        $this->assertTrue($target->teams()->whereKey($team)->exists());
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $target->getMorphClass(),
            'subject_id' => $target->id,
            'event' => 'spatial_assignment_updated',
        ]);
    }

    public function test_user_without_assignment_permission_cannot_update_spatial_assignment(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->for($branch)->create();
        $actor = User::factory()->for($branch)->create();
        $target = User::factory()->for($branch)->create();

        $this->actingAs($actor)
            ->patchJson(route('admin.spatial-workspace.users.update', $target), ['team_id' => $team->id])
            ->assertForbidden();
    }
}
