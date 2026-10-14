<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_grouped_human_readable_permissions(): void
    {
        Rbac::provision();
        $superAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdmin->globalRoles()->attach($role);

        $response = $this->actingAs($superAdmin)->get(route('admin.roles.edit', $role));

        $response
            ->assertOk()
            ->assertSee('Buku Saku Sales')
            ->assertSee('Lihat lead — Milik saya')
            ->assertSee('Atur cabang, tim, dan akses pengguna')
            ->assertDontSee('>leads.view-own</span>')
            ->assertDontSee('>users.assign-roles</span>');
    }
}
