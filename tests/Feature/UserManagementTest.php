<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_index_can_be_searched_and_filtered_by_branch_and_role(): void
    {
        Rbac::provision();
        $admin = $this->superAdmin();
        $magelang = Branch::factory()->create(['name' => 'Magelang']);
        $semarang = Branch::factory()->create(['name' => 'Semarang']);
        $adminRole = Role::query()->whereNull('team_id')->where('name', 'sas')->firstOrFail();

        $first = User::factory()->for($magelang)->create(['name' => 'Arif Pratama', 'email' => 'arif@example.com']);
        $second = User::factory()->for($semarang)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $first->globalRoles()->attach($adminRole);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'arif']))
            ->assertOk()
            ->assertSee('Arif Pratama')
            ->assertDontSee('Budi Santoso');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['branch_id' => $semarang->id]))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Arif Pratama');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['global_role_id' => $adminRole->id]))
            ->assertOk()
            ->assertSee('Arif Pratama')
            ->assertDontSee('Budi Santoso');
    }

    public function test_super_admin_can_bulk_assign_users_to_a_branch(): void
    {
        Rbac::provision();
        $admin = $this->superAdmin();
        $targetBranch = Branch::factory()->create(['name' => 'Target']);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), [
                'action' => 'assign-branch',
                'branch_id' => $targetBranch->id,
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame($targetBranch->id, $first->fresh()->branch_id);
        $this->assertSame($targetBranch->id, $second->fresh()->branch_id);
    }

    public function test_bulk_delete_skips_acting_user_and_last_super_admin(): void
    {
        Rbac::provision();
        $admin = $this->superAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), [
                'action' => 'delete',
                'ids' => [$admin->id, $target->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_without_permission_cannot_run_bulk_actions(): void
    {
        Rbac::provision();
        $user = User::factory()->create();
        $target = User::factory()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.users.bulk'), [
                'action' => 'assign-branch',
                'branch_id' => $branch->id,
                'ids' => [$target->id],
            ])
            ->assertForbidden();

        $this->assertNull($target->fresh()->branch_id);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $user->globalRoles()->attach($role);

        return $user;
    }
}
