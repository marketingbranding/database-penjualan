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
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
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
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        $this->actingAs($admin)
            ->get(route('consumers.index'))
            ->assertSeeText('Dashboard')
            ->assertSeeText('Data konsumen')
            ->assertSeeText('Proses penjualan')
            ->assertSeeText('Alur kavling')
            ->assertSeeText('BI Checking')
            ->assertSeeText('Pemberkasan')
            ->assertSeeText('Proses bank')
            ->assertSeeText('PPJB developer')
            ->assertSeeText('Akad dan BAST')
            ->assertSeeText('Garansi')
            ->assertSeeText('Pengaturan')
            ->assertSeeText('Cabang')
            ->assertSeeText('Proyek')
            ->assertSeeText('Kavling')
            ->assertSeeText('Bank')
            ->assertSeeText('Promo')
            ->assertDontSeeText('Performa sales');
    }

    public function test_monitoring_page_only_marks_monitoring_link_as_active(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        $response = $this->actingAs($admin)->get(route('leads.monitoring'));
        $content = $response->getContent();

        preg_match('/<a href="[^"]*\/leads" class="([^"]+)"[^>]*>.*?Leads.*?<\/a>/s', $content, $leadsLink);
        preg_match('/<a href="[^"]*\/leads\/monitoring" class="([^"]+)"[^>]*>.*?Monitoring leads.*?<\/a>/s', $content, $monitoringLink);

        $this->assertNotEmpty($leadsLink);
        $this->assertNotEmpty($monitoringLink);
        $this->assertStringNotContainsString('bg-lime-soft', $leadsLink[1]);
        $this->assertStringContainsString('bg-lime-soft', $monitoringLink[1]);
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

    public function test_koordinator_can_open_dashboard_but_not_sales_process_or_consumer_data(): void
    {
        Rbac::provision();
        $team = Team::factory()->create();
        Rbac::provisionTeam($team);
        $koordinator = User::factory()->create();
        $koordinator->teams()->attach($team);

        setPermissionsTeamId($team->id);
        $role = Role::query()->where('team_id', $team->id)->where('name', 'koordinator_sales')->firstOrFail();
        $koordinator->assignRole($role);

        $this->actingAs($koordinator)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($koordinator)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('sales-process.bi-checking'))
            ->assertForbidden();

        $this->actingAs($koordinator)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('consumers.index'))
            ->assertForbidden();
    }
}
