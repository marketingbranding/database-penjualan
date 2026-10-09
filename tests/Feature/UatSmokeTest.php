<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UatSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_uat_seed_contains_expected_accounts_and_business_data(): void
    {
        $this->seed(UatSeeder::class);

        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('branches', 2);
        $this->assertDatabaseCount('teams', 2);
        $this->assertDatabaseCount('projects', 2);
        $this->assertDatabaseCount('promotions', 2);
        $this->assertDatabaseCount('leads', 8);
        $this->assertDatabaseCount('lead_activities', 15);
        $this->assertDatabaseCount('sales_agendas', 4);
        $this->assertDatabaseCount('sales_cases', 1);
    }

    public function test_uat_role_smoke_paths_are_available_or_forbidden_as_expected(): void
    {
        $this->seed(UatSeeder::class);
        $sales = User::query()->where('email', 'uat.sales.andi@example.test')->firstOrFail();
        $coordinator = User::query()->where('email', 'uat.coord.ked@example.test')->firstOrFail();
        $sas = User::query()->where('email', 'uat.sas@example.test')->firstOrFail();
        $admin = User::query()->where('email', 'uat.admin@example.test')->firstOrFail();

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $sales->teams()->firstOrFail()->id])
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('261001-ON-TI-01')
            ->assertDontSee('261006-ON-FB-01');

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $sales->teams()->firstOrFail()->id])
            ->get(route('sales-fee-reports.index'))
            ->assertForbidden();

        $this->actingAs($coordinator)
            ->withSession(['active_team_id' => $coordinator->teams()->firstOrFail()->id])
            ->get(route('leads.monitoring'))
            ->assertOk()
            ->assertSee('261003-OF-CA-01');

        $this->actingAs($sas)
            ->get(route('sales-fee-reports.index'))
            ->assertOk()
            ->assertSee('UAT Sales Andi');

        $this->actingAs($sas)
            ->get(route('leads.create'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('sales-fee-reports.export'))
            ->assertDownload();
    }

    public function test_uat_seeder_is_repeatable_by_business_keys(): void
    {
        $this->seed(UatSeeder::class);
        $this->seed(UatSeeder::class);

        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('leads', 8);
        $this->assertDatabaseCount('sales_agendas', 4);
        $this->assertDatabaseHas('leads', ['id_lead' => '261008-LC-DL-01', 'status_lead' => 'Akad']);
    }
}
