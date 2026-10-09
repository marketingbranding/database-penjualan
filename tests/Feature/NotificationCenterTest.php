<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BranchAssignmentChangedNotification;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_lot_status_change_creates_database_notification_for_branch_users(): void
    {
        [$admin, $lot] = $this->branchAdminWithLot();

        $this->actingAs($admin)
            ->patch(route('admin.lots.update', $lot), ['status' => LotStatus::Reserved->value])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => $admin->getMorphClass(),
            'read_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('Status kavling berubah');
    }

    public function test_user_can_mark_own_notification_as_read_but_not_another_users_notification(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->notify(new BranchAssignmentChangedNotification($branch));
        $otherUser->notify(new BranchAssignmentChangedNotification($branch));

        $ownNotification = $user->unreadNotifications()->firstOrFail();
        $otherNotification = $otherUser->unreadNotifications()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('notifications.read', $ownNotification->id))
            ->assertRedirect();
        $this->assertNotNull($ownNotification->fresh()->read_at);

        $this->actingAs($user)
            ->patch(route('notifications.read', $otherNotification->id))
            ->assertNotFound();
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $user->notify(new BranchAssignmentChangedNotification($branch));
        $user->notify(new BranchAssignmentChangedNotification(null));

        $this->actingAs($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    /** @return array{User, Lot} */
    private function branchAdminWithLot(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => 'A']);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => 1,
            'code' => 'A01',
            'status' => LotStatus::Available,
        ]);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return [$admin, $lot];
    }
}
