<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_can_open_all_module_containers(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        $routes = [
            'consumers.index',
            'sales-process.psjb-utj',
            'sales-process.bi-checking',
            'sales-process.pemberkasan',
            'sales-process.bank',
            'sales-process.ppjb-developer',
            'sales-process.akad-bast',
            'warranties.index',
            'admin.branches.index',
            'admin.projects.index',
            'admin.lots.index',
            'admin.banks.index',
            'admin.promotions.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_sidebar_contains_the_requested_module_hierarchy(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertSeeText('Dashboard')
            ->assertSeeText('Data Konsumen')
            ->assertSeeText('Proses Penjualan')
            ->assertSeeText('PSJB / UTJ')
            ->assertSeeText('BI Checking')
            ->assertSeeText('Pemberkasan')
            ->assertSeeText('Proses Bank')
            ->assertSeeText('PPJB Developer')
            ->assertSeeText('Akad & BAST')
            ->assertSeeText('Garansi')
            ->assertSeeText('Pengaturan')
            ->assertSeeText('Cabang')
            ->assertSeeText('Proyek')
            ->assertSeeText('Kavling')
            ->assertSeeText('Bank')
            ->assertSeeText('Promo');
    }

    public function test_user_without_permission_cannot_open_module_containers(): void
    {
        Rbac::provision();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('consumers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('sales-process.psjb-utj'))->assertForbidden();
        $this->actingAs($user)->get(route('warranties.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.branches.index'))->assertForbidden();
    }

    public function test_supervisor_can_open_sales_process_but_not_consumer_data(): void
    {
        Rbac::provision();
        $team = Team::factory()->create();
        Rbac::provisionTeam($team);
        $supervisor = User::factory()->create();
        $supervisor->teams()->attach($team);

        setPermissionsTeamId($team->id);
        $role = Role::query()->where('team_id', $team->id)->where('name', 'supervisor')->firstOrFail();
        $supervisor->assignRole($role);

        $this->actingAs($supervisor)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('sales-process.bi-checking'))
            ->assertOk();

        $this->actingAs($supervisor)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('consumers.index'))
            ->assertForbidden();
    }
}
