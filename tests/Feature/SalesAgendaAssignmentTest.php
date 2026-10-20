<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesAgendaEvidence;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SalesAgendaAssignmentNotification;
use App\SalesFeeReportService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SalesAgendaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_agenda_owner_defaults_to_creator(): void
    {
        $creator = User::factory()->create();

        $agenda = SalesAgenda::factory()->create(['created_by' => $creator->id]);

        $this->assertSame($creator->id, $agenda->owner_id);
    }

    public function test_coordinator_can_create_an_agenda_for_a_sales_and_notifies_only_new_owner(): void
    {
        [$coordinator, $sales, $team] = $this->coordinatorContext();
        Notification::fake();

        $this->actingAs($coordinator)->withSession(['active_team_id' => $team->id])->post(route('sales-agendas.store'), [
            'owner_id' => $sales->id,
            'agenda_date' => '2026-10-15',
            'activity_category' => 'Follow-up',
            'title' => 'Agenda untuk Sales',
            'plan_detail' => 'Follow-up Lead untuk Sales.',
            'duration_minutes' => 30,
        ])->assertRedirect();

        $agenda = SalesAgenda::query()->latest('id')->firstOrFail();
        $this->assertSame($coordinator->id, $agenda->created_by);
        $this->assertSame($sales->id, $agenda->owner_id);
        $this->assertDatabaseHas('sales_agenda_assignment_histories', ['action' => 'created_for', 'to_user_id' => $sales->id]);
        Notification::assertSentTo($sales, SalesAgendaAssignmentNotification::class);
        Notification::assertNotSentTo($coordinator, SalesAgendaAssignmentNotification::class);
    }

    public function test_coordinator_cannot_create_an_agenda_for_sales_outside_their_team(): void
    {
        [$coordinator, , $team] = $this->coordinatorContext();
        $otherTeam = Team::factory()->create(['branch_id' => $team->branch_id]);
        $otherSales = $this->teamUser($team->branch, $otherTeam, 'sales');

        $this->actingAs($coordinator)->withSession(['active_team_id' => $team->id])->post(route('sales-agendas.store'), [
            'owner_id' => $otherSales->id,
            'agenda_date' => '2026-10-15',
            'activity_category' => 'Follow-up',
            'title' => 'Agenda lintas tim',
            'plan_detail' => 'Follow-up Lead lintas tim.',
            'duration_minutes' => 30,
        ])->assertSessionHasErrors('owner_id');
    }

    public function test_coordinator_can_redirect_with_reason_and_notifies_both_owners(): void
    {
        [$coordinator, $oldOwner, $team] = $this->coordinatorContext();
        $newOwner = $this->teamUser($team->branch, $team, 'sales');
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $oldOwner->id, 'owner_id' => $oldOwner->id]);
        Notification::fake();

        $this->actingAs($coordinator)->patch(route('sales-agendas.redirect', $agenda), [
            'owner_id' => $newOwner->id,
            'reason' => 'Pembagian wilayah berubah.',
        ])->assertRedirect(route('sales-agendas.show', $agenda));

        $agenda->refresh();
        $this->assertSame($newOwner->id, $agenda->owner_id);
        $this->assertDatabaseHas('sales_agenda_assignment_histories', ['action' => 'redirected', 'from_user_id' => $oldOwner->id, 'to_user_id' => $newOwner->id, 'reason' => 'Pembagian wilayah berubah.']);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => $agenda->getMorphClass(), 'subject_id' => $agenda->id, 'event' => 'agenda_redirected']);
        Notification::assertSentTo($oldOwner, SalesAgendaAssignmentNotification::class);
        Notification::assertSentTo($newOwner, SalesAgendaAssignmentNotification::class);
    }

    public function test_redirect_requires_same_team_planned_without_evidence_and_reason(): void
    {
        [$coordinator, $oldOwner, $team] = $this->coordinatorContext();
        $newOwner = $this->teamUser($team->branch, $team, 'sales');
        $otherTeam = Team::factory()->create(['branch_id' => $team->branch_id]);
        $foreignSales = $this->teamUser($team->branch, $otherTeam, 'sales');
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $oldOwner->id, 'owner_id' => $oldOwner->id]);

        $this->actingAs($coordinator)->patch(route('sales-agendas.redirect', $agenda), ['owner_id' => $newOwner->id])->assertSessionHasErrors('reason');
        $this->actingAs($coordinator)->patch(route('sales-agendas.redirect', $agenda), ['owner_id' => $foreignSales->id, 'reason' => 'Alasan'])->assertSessionHasErrors('owner_id');

        SalesAgendaEvidence::factory()->create(['sales_agenda_id' => $agenda->id]);
        $this->actingAs($coordinator)->patch(route('sales-agendas.redirect', $agenda), ['owner_id' => $newOwner->id, 'reason' => 'Alasan'])->assertSessionHasErrors('agenda');

        $agenda->update(['status' => 'done']);
        $this->actingAs($coordinator)->patch(route('sales-agendas.redirect', $agenda), ['owner_id' => $newOwner->id, 'reason' => 'Alasan'])->assertSessionHasErrors('status');
    }

    public function test_sales_and_branch_roles_cannot_redirect_but_can_read_history(): void
    {
        [$coordinator, $oldOwner, $team] = $this->coordinatorContext();
        $newOwner = $this->teamUser($team->branch, $team, 'sales');
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $oldOwner->id, 'owner_id' => $oldOwner->id]);
        $agenda->assignmentHistories()->create(['action' => 'redirected', 'from_user_id' => $oldOwner->id, 'to_user_id' => $newOwner->id, 'by_user_id' => $coordinator->id, 'reason' => 'Riwayat uji']);

        foreach ([$oldOwner, $this->globalUser($team->branch, 'spv'), $this->globalUser($team->branch, 'bm'), $this->globalUser($team->branch, 'sas'), $this->globalUser($team->branch, 'sap')] as $viewer) {
            $this->actingAs($viewer)->get(route('sales-agendas.show', $agenda))->assertOk()->assertSee('Riwayat uji');
            $this->actingAs($viewer)->get(route('sales-agendas.redirect-form', $agenda))->assertForbidden();
        }
    }

    public function test_coordinator_can_view_agenda_evidence_from_read_only_detail(): void
    {
        [$coordinator, $owner, $team] = $this->coordinatorContext();
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $owner->id, 'owner_id' => $owner->id]);
        SalesAgendaEvidence::factory()->create(['sales_agenda_id' => $agenda->id, 'original_name' => 'team-evidence.jpg']);

        $this->actingAs($coordinator)
            ->get(route('sales-agendas.show', $agenda))
            ->assertOk()
            ->assertSee('team-evidence.jpg');
    }

    public function test_previous_owner_sees_redirected_history_but_cannot_edit_delete_complete_or_upload(): void
    {
        [$coordinator, $oldOwner, $team] = $this->coordinatorContext();
        $newOwner = $this->teamUser($team->branch, $team, 'sales');
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $oldOwner->id, 'owner_id' => $newOwner->id]);
        $agenda->assignmentHistories()->create(['action' => 'redirected', 'from_user_id' => $oldOwner->id, 'to_user_id' => $newOwner->id, 'by_user_id' => $coordinator->id, 'reason' => 'Pemindahan tugas']);

        $this->actingAs($oldOwner)->get(route('sales-agendas.index'))->assertDontSee($agenda->title);
        $this->actingAs($oldOwner)->get(route('sales-agendas.index', ['view' => 'redirected']))->assertSee('Pemindahan tugas')->assertSee('Dialihkan ke');
        $this->actingAs($oldOwner)->get(route('sales-agendas.edit', $agenda))->assertForbidden();
        $this->actingAs($oldOwner)->patch(route('sales-agendas.complete', $agenda), ['activity_result' => 'Selesai'])->assertForbidden();
        $this->actingAs($oldOwner)->put(route('sales-agendas.update', $agenda), [
            'agenda_date' => $agenda->agenda_date->toDateString(), 'activity_category' => $agenda->activity_category,
            'title' => $agenda->title, 'evidence' => [UploadedFile::fake()->image('bukti.jpg')],
        ])->assertForbidden();
        $this->actingAs($oldOwner)->delete(route('sales-agendas.destroy', $agenda))->assertForbidden();
    }

    public function test_new_owner_can_complete_and_fee_report_credits_owner(): void
    {
        [$coordinator, $oldOwner, $team] = $this->coordinatorContext();
        $newOwner = $this->teamUser($team->branch, $team, 'sales');
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $oldOwner->id, 'owner_id' => $newOwner->id]);
        $this->actingAs($newOwner)->patch(route('sales-agendas.complete', $agenda), ['activity_result' => 'Selesai'])->assertRedirect();

        $this->assertDatabaseHas('sales_agendas', ['id' => $agenda->id, 'status' => 'done', 'owner_id' => $newOwner->id]);
        $report = app(SalesFeeReportService::class)->build($coordinator, [
            'branch_id' => $team->branch_id,
            'date_from' => $agenda->agenda_date->toDateString(),
            'date_to' => $agenda->agenda_date->toDateString(),
            'project_id' => null,
            'coordinator_id' => null,
            'sales_pic_id' => null,
        ]);
        $this->assertTrue($report['rows']->contains(fn (array $row): bool => $row['sales_name'] === $newOwner->name));
    }

    public function test_coordinator_cancel_requires_reason_and_writes_history(): void
    {
        [$coordinator, $owner, $team] = $this->coordinatorContext();
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $owner->id, 'owner_id' => $owner->id]);

        $this->actingAs($coordinator)->patch(route('sales-agendas.cancel-by-coordinator', $agenda))->assertSessionHasErrors('reason');
        $this->actingAs($coordinator)->patch(route('sales-agendas.cancel-by-coordinator', $agenda), ['reason' => 'Tidak tersedia'])->assertRedirect(route('sales-agendas.show', $agenda));

        $this->assertDatabaseHas('sales_agendas', ['id' => $agenda->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('sales_agenda_assignment_histories', ['action' => 'cancelled_by_coordinator', 'reason' => 'Tidak tersedia']);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => $agenda->getMorphClass(), 'subject_id' => $agenda->id, 'event' => 'agenda_cancelled_by_coordinator']);
    }

    public function test_coordinator_cannot_cancel_an_agenda_with_evidence(): void
    {
        [$coordinator, $owner, $team] = $this->coordinatorContext();
        $agenda = SalesAgenda::factory()->create(['branch_id' => $team->branch_id, 'team_id' => $team->id, 'created_by' => $owner->id, 'owner_id' => $owner->id]);
        SalesAgendaEvidence::factory()->create(['sales_agenda_id' => $agenda->id]);

        $this->actingAs($coordinator)
            ->patch(route('sales-agendas.cancel-by-coordinator', $agenda), ['reason' => 'Tidak tersedia'])
            ->assertSessionHasErrors('agenda');

        $this->assertDatabaseHas('sales_agendas', ['id' => $agenda->id, 'status' => 'planned']);
    }

    /** @return array{0: User, 1: User, 2: Team} */
    private function coordinatorContext(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->create(['branch_id' => $branch->id]);
        $coordinator = $this->teamUser($branch, $team, 'koordinator_sales');
        $team->update(['coordinator_id' => $coordinator->id]);
        $owner = $this->teamUser($branch, $team, 'sales');

        return [$coordinator, $owner, $team];
    }

    private function teamUser(Branch $branch, Team $team, string $roleName): User
    {
        Rbac::provisionTeam($team);
        $user = User::factory()->for($branch, 'branch')->create();
        $user->teams()->attach($team, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $user->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }

    private function globalUser(Branch $branch, string $roleName): User
    {
        $user = User::factory()->for($branch, 'branch')->create();
        $user->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', $roleName)->firstOrFail());

        return $user;
    }
}
