<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\PaymentMethod;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_admin_dashboard_only_counts_assigned_branch(): void
    {
        Rbac::provision();
        [$branch, $project] = $this->projectContext('Magelang');
        [, $otherProject] = $this->projectContext('Semarang');
        $this->createSale($project, 'A01', PaymentMethod::Kpr);
        $this->createSale($otherProject, 'B01', PaymentMethod::Cash);
        Lot::factory()->for($project)->for(ProjectBlock::factory()->for($project), 'block')->create([
            'code' => 'C01', 'number' => 1, 'status' => LotStatus::Available,
        ]);

        $admin = User::factory()->for($branch)->create();
        $adminRole = Role::query()->whereNull('team_id')->where('name', 'bm')->firstOrFail();
        $admin->globalRoles()->attach($adminRole);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['consumers'] === 1
                && $metrics['kpr'] === 1
                && $metrics['cash'] === 0
                && $metrics['availableLots'] === 1);
    }

    public function test_super_admin_can_filter_dashboard_by_branch(): void
    {
        Rbac::provision();
        [$branch, $project] = $this->projectContext('Magelang');
        [, $otherProject] = $this->projectContext('Semarang');
        $this->createSale($project, 'A01', PaymentMethod::Kpr);
        $this->createSale($otherProject, 'B01', PaymentMethod::Cash);

        $superAdmin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdmin->globalRoles()->attach($role);

        $this->actingAs($superAdmin)
            ->get(route('dashboard', ['branch_id' => $branch->id]))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['consumers'] === 1
                && $metrics['kpr'] === 1
                && $metrics['cash'] === 0);
    }

    /** @return array{Branch, Project} */
    private function projectContext(string $branchName): array
    {
        $branch = Branch::factory()->create(['name' => $branchName]);
        $project = Project::factory()->for($branch)->create(['name' => $branchName.' Residence']);

        return [$branch, $project];
    }

    private function createSale(Project $project, string $code, PaymentMethod $method): SalesCase
    {
        $block = ProjectBlock::factory()->for($project)->create(['code' => $code[0]]);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'code' => $code,
            'number' => (int) substr($code, 1),
            'status' => LotStatus::Reserved,
        ]);

        return SalesCase::factory()
            ->for(Consumer::factory())
            ->for($lot)
            ->create(['payment_method' => $method]);
    }
}
