<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_branch_project_and_generate_lots_for_a_block(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.branches.store'), [
            'code' => 'MGL',
            'name' => 'Magelang',
            'is_active' => true,
        ])->assertRedirect();

        $branch = Branch::query()->firstOrFail();
        $this->assertSame('CAB-001', $branch->code);

        $this->actingAs($admin)->post(route('admin.projects.store'), [
            'branch_id' => $branch->id,
            'code' => 'MKG',
            'name' => 'Marison Kalinegoro',
            'is_active' => true,
        ])->assertRedirect();

        $project = Project::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.projects.blocks.store', $project), [
            'code' => 'Z',
            'lot_count' => 20,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('lots', 20);
        $this->assertDatabaseHas('lots', ['project_id' => $project->id, 'code' => 'A01']);
        $this->assertDatabaseHas('lots', ['project_id' => $project->id, 'code' => 'A20']);
    }

    public function test_block_codes_are_generated_in_order_per_project(): void
    {
        $admin = $this->adminUser();
        $firstProject = Project::factory()->create();
        $secondProject = Project::factory()->create();

        $payload = ['lot_count' => 2, 'is_active' => true];

        $this->actingAs($admin)->post(route('admin.projects.blocks.store', $firstProject), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.projects.blocks.store', $secondProject), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.projects.blocks.store', $firstProject), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_blocks', ['project_id' => $firstProject->id, 'code' => 'A']);
        $this->assertDatabaseHas('project_blocks', ['project_id' => $firstProject->id, 'code' => 'B']);
        $this->assertDatabaseHas('project_blocks', ['project_id' => $secondProject->id, 'code' => 'A']);
        $this->assertDatabaseCount('project_blocks', 3);
    }

    public function test_lot_list_is_filtered_by_branch_and_project(): void
    {
        $admin = $this->adminUser();
        $magelang = Branch::factory()->create(['name' => 'Magelang']);
        $semarang = Branch::factory()->create(['name' => 'Semarang']);
        $kalinegoro = Project::factory()->for($magelang)->create(['name' => 'Kalinegoro']);
        $otherProject = Project::factory()->for($semarang)->create(['name' => 'Mijen']);
        $blockA = ProjectBlock::factory()->for($kalinegoro)->create(['code' => 'A']);
        $blockB = ProjectBlock::factory()->for($otherProject)->create(['code' => 'B']);

        Lot::factory()->for($kalinegoro)->for($blockA, 'block')->create(['number' => 1, 'code' => 'A01']);
        Lot::factory()->for($otherProject)->for($blockB, 'block')->create(['number' => 1, 'code' => 'B01']);

        $this->actingAs($admin)
            ->get(route('admin.lots.index', ['branch_id' => $magelang->id, 'project_id' => $kalinegoro->id]))
            ->assertOk()
            ->assertSeeText('A01')
            ->assertDontSeeText('B01');
    }

    public function test_admin_can_update_lot_status(): void
    {
        $admin = $this->adminUser();
        $project = Project::factory()->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'A']);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 1,
            'code' => 'A01',
            'status' => LotStatus::Available,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.lots.update', $lot), ['status' => LotStatus::Reserved->value])
            ->assertRedirect();

        $this->assertSame(LotStatus::Reserved, $lot->fresh()->status);
    }

    public function test_block_with_processed_lot_cannot_be_deleted(): void
    {
        $admin = $this->adminUser();
        $project = Project::factory()->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'A']);
        Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 1,
            'code' => 'A01',
            'status' => LotStatus::Sold,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.projects.blocks.destroy', [$project, $block]))
            ->assertSessionHasErrors('block');

        $this->assertNotNull($block->fresh());
    }

    public function test_user_without_permissions_cannot_manage_property_inventory(): void
    {
        Rbac::provision();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.branches.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.projects.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.lots.index'))->assertForbidden();
    }

    private function adminUser(): User
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return $admin;
    }
}
