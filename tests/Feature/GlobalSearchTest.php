<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_search_leads(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());
        $sales = User::factory()->for($branch, 'branch')->create();
        $lead = Lead::factory()->create([
            'id_lead' => '261001-ON-TI-01',
            'branch_id' => $branch->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'nama_konsumen' => 'Searchable Consumer',
        ]);

        $this->actingAs($admin)
            ->get(route('search', ['q' => 'Searchable']))
            ->assertOk()
            ->assertSee($lead->id_lead)
            ->assertSee('Searchable Consumer');
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());

        $this->actingAs($admin)
            ->get(route('search', ['q' => 'a']))
            ->assertOk()
            ->assertSee('Masukkan minimal 2 karakter');
    }

    public function test_authenticated_layout_exposes_search_on_mobile(): void
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Buka pencarian"', false)
            ->assertSee('href="'.route('search').'"', false);
    }
}
