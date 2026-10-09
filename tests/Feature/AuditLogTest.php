<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_lot_status_change_is_audited_and_added_to_history(): void
    {
        [$admin, $lot] = $this->branchAdminWithLot();

        $this->actingAs($admin)
            ->patch(route('admin.lots.update', $lot), ['status' => LotStatus::Reserved->value])
            ->assertRedirect();

        $log = ActivityLog::query()->where('subject_type', $lot->getMorphClass())->where('subject_id', $lot->id)->firstOrFail();
        $this->assertSame('updated', $log->event);
        $this->assertSame(LotStatus::Available->value, $log->old_values['status']);
        $this->assertSame(LotStatus::Reserved->value, $log->new_values['status']);
        $this->assertSame($admin->id, $log->user_id);

        $this->assertDatabaseHas('lot_status_histories', [
            'lot_id' => $lot->id,
            'user_id' => $admin->id,
            'from_status' => LotStatus::Available->value,
            'to_status' => LotStatus::Reserved->value,
            'source' => 'manual',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lots.history', $lot))
            ->assertOk()
            ->assertSee('Tersedia')
            ->assertSee('Dipesan');
    }

    public function test_super_admin_sees_audit_logs_across_branches_and_can_filter_by_branch(): void
    {
        [$magelangAdmin, $magelangLot] = $this->branchAdminWithLot('Magelang', 'A01');
        [$semarangAdmin, $semarangLot] = $this->branchAdminWithLot('Semarang', 'B01');

        $this->actingAs($magelangAdmin)->patch(route('admin.lots.update', $magelangLot), ['status' => LotStatus::Reserved->value]);
        $this->actingAs($semarangAdmin)->patch(route('admin.lots.update', $semarangLot), ['status' => LotStatus::Reserved->value]);

        $this->actingAs($magelangAdmin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 2);

        $this->actingAs($magelangAdmin)
            ->get(route('admin.audit-logs.index', ['branch_id' => $magelangAdmin->branch_id]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 1
                && $logs->first()->branch_id === $magelangAdmin->branch_id);
    }

    public function test_unauthorized_user_cannot_open_audit_log(): void
    {
        Rbac::provision();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    /** @return array{User, Lot} */
    private function branchAdminWithLot(string $branchName = 'Magelang', string $lotCode = 'A01'): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => $branchName]);
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => $lotCode[0]]);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => (int) substr($lotCode, 1),
            'code' => $lotCode,
            'status' => LotStatus::Available,
        ]);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return [$admin, $lot];
    }
}
