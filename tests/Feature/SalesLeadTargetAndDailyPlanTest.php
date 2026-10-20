<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadCutoverDisposition;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesDailyPlan;
use App\Models\SalesLeadTarget;
use App\Models\Team;
use App\Models\User;
use App\Notifications\DailyPlanSubmittedNotification;
use App\Notifications\SalesAgendaReminderNotification;
use App\Services\DailyAgendaPlanner;
use App\Services\SalesDailyPlanService;
use App\Services\SalesLeadTargetService;
use App\Support\BusinessTime;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SalesLeadTargetAndDailyPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_progress_uses_sales_pic_and_excludes_approved_operational_disposition(): void
    {
        [$branch, $team, $sales, $coordinator] = $this->teamContext();
        $period = '2026-10-15';
        SalesLeadTarget::factory()->create(['sales_id' => $sales->id, 'period_year' => 2026, 'period_month' => 10, 'lead_target' => 40]);
        $assignedByCoordinator = $this->lead($branch, $team, $sales, $coordinator, '260615-ON-TI-01', $period);
        $assignedToAnotherSales = $this->teamUser($branch, $team, 'sales');
        $this->lead($branch, $team, $assignedToAnotherSales, $sales, '260615-ON-TI-02', $period);
        $excluded = $this->lead($branch, $team, $sales, $coordinator, '260615-ON-TI-03', $period);
        LeadCutoverDisposition::query()->create([
            'lead_id' => $excluded->id,
            'classification' => LeadCutoverDisposition::EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED,
            'cutover_at' => '2026-10-20 00:00:00',
            'current_month_boundary' => '2026-10-01 00:00:00',
            'reason' => 'Approved historical exception',
        ]);

        $progress = app(SalesLeadTargetService::class)->progress($sales, $period);

        $this->assertSame(1, $progress['achieved']);
        $this->assertSame(40, $progress['target']);
        $this->assertModelExists($assignedByCoordinator);
    }

    public function test_planner_resumes_after_lunch_and_preserves_effective_duration(): void
    {
        [$branch, $team, $sales] = $this->teamContext();
        $plan = SalesDailyPlan::query()->create(['sales_id' => $sales->id, 'team_id' => $team->id, 'plan_date' => '2026-10-20', 'status' => 'draft']);
        $first = $this->agenda($branch, $team, $sales, $plan, 150, 1, 'Pagi');
        $second = $this->agenda($branch, $team, $sales, $plan, 90, 2, 'Melintasi makan siang');

        app(DailyAgendaPlanner::class)->recalculate($plan);
        $first->refresh();
        $second->refresh();

        $this->assertSame('09:00', BusinessTime::format($first->planned_start_at, 'H:i'));
        $this->assertSame('11:30', BusinessTime::format($first->planned_end_at, 'H:i'));
        $this->assertSame('11:30', BusinessTime::format($second->planned_start_at, 'H:i'));
        $this->assertSame('14:00', BusinessTime::format($second->planned_end_at, 'H:i'));
        $this->assertSame(240, app(DailyAgendaPlanner::class)->effectiveMinutes($plan));
    }

    public function test_sales_can_submit_exactly_420_minutes_and_only_final_coordinator_is_notified(): void
    {
        [$branch, $team, $sales, $coordinator] = $this->teamContext();
        $plan = SalesDailyPlan::query()->create(['sales_id' => $sales->id, 'team_id' => $team->id, 'plan_date' => '2026-10-20', 'status' => 'draft']);
        $this->agenda($branch, $team, $sales, $plan, 210, 1, 'Pagi');
        $this->agenda($branch, $team, $sales, $plan, 210, 2, 'Sore');
        Notification::fake();

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('daily-plans.submit', $plan))
            ->assertRedirect(route('daily-plans.show', $plan));

        $this->assertDatabaseHas('sales_daily_plans', ['id' => $plan->id, 'status' => 'submitted', 'submitted_by' => $sales->id]);
        Notification::assertSentTo($coordinator, DailyPlanSubmittedNotification::class);
    }

    public function test_submitted_plan_cannot_be_left_with_an_invalid_total_by_coordinator_edit(): void
    {
        [$branch, $team, $sales, $coordinator] = $this->teamContext();
        $plan = SalesDailyPlan::query()->create(['sales_id' => $sales->id, 'team_id' => $team->id, 'plan_date' => '2026-10-20', 'status' => 'draft']);
        $agenda = $this->agenda($branch, $team, $sales, $plan, 210, 1, 'Pagi');
        $this->agenda($branch, $team, $sales, $plan, 210, 2, 'Sore');
        app(SalesDailyPlanService::class)->submit($plan, $sales);

        $this->actingAs($coordinator)
            ->withSession(['active_team_id' => $team->id])
            ->put(route('sales-agendas.update', $agenda), [
                'agenda_date' => '2026-10-20',
                'activity_category' => 'Follow-up',
                'title' => 'Pagi diperbarui',
                'plan_detail' => 'Durasi sengaja dibuat invalid.',
                'duration_minutes' => 200,
            ])
            ->assertSessionHasErrors('duration_minutes');

        $this->assertSame(210, $agenda->fresh()->duration_minutes);
        $this->assertSame('submitted', $plan->fresh()->status);
    }

    public function test_reminder_command_is_idempotent_for_the_same_occurrence(): void
    {
        [$branch, $team, $sales] = $this->teamContext();
        $now = BusinessTime::now();
        $plan = SalesDailyPlan::query()->create(['sales_id' => $sales->id, 'team_id' => $team->id, 'plan_date' => $now->toDateString(), 'status' => 'draft']);
        SalesAgenda::query()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'daily_plan_id' => $plan->id,
            'created_by' => $sales->id,
            'owner_id' => $sales->id,
            'agenda_date' => $now->toDateString(),
            'activity_category' => 'Follow-up',
            'title' => 'Reminder test',
            'plan_detail' => 'Reminder detail.',
            'duration_minutes' => 60,
            'sort_order' => 1,
            'planned_start_at' => $now->addMinutes(10)->utc(),
            'planned_end_at' => $now->addMinutes(70)->utc(),
            'status' => 'planned',
        ]);
        Notification::fake();

        $this->artisan('sales:send-agenda-reminders')->assertSuccessful();
        $this->artisan('sales:send-agenda-reminders')->assertSuccessful();

        $this->assertDatabaseCount('sales_agenda_notification_deliveries', 1);
        Notification::assertSentTo($sales, SalesAgendaReminderNotification::class);
    }

    public function test_super_admin_can_create_and_edit_monthly_target_but_team_users_cannot_edit_it(): void
    {
        [$branch, $team, $sales, $coordinator] = $this->teamContext();
        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());

        $this->actingAs($admin)->post(route('admin.sales-lead-targets.store'), [
            'sales_id' => $sales->id,
            'period_year' => 2026,
            'period_month' => 10,
            'lead_target' => 40,
        ])->assertRedirect();

        $target = SalesLeadTarget::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.sales-lead-targets.update', $target), ['lead_target' => 50])->assertRedirect();
        $this->assertSame(50, $target->fresh()->lead_target);

        $this->actingAs($sales)->patch(route('admin.sales-lead-targets.update', $target), ['lead_target' => 60])->assertForbidden();
        $this->actingAs($coordinator)->patch(route('admin.sales-lead-targets.update', $target), ['lead_target' => 60])->assertForbidden();
        $this->assertSame(50, $target->fresh()->lead_target);
    }

    public function test_coordinator_buku_saku_lists_only_plans_from_their_team(): void
    {
        [$branch, $team, $sales, $coordinator] = $this->teamContext();
        $otherBranch = Branch::factory()->create();
        $otherTeam = Team::factory()->for($otherBranch)->create();
        Rbac::provisionTeam($otherTeam);
        $otherSales = $this->teamUser($otherBranch, $otherTeam, 'sales');
        $date = '2026-10-20';
        SalesDailyPlan::factory()->create(['sales_id' => $sales->id, 'team_id' => $team->id, 'plan_date' => $date]);
        SalesDailyPlan::factory()->create(['sales_id' => $otherSales->id, 'team_id' => $otherTeam->id, 'plan_date' => $date]);

        $this->actingAs($coordinator)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('daily-plans.index', ['date' => $date]))
            ->assertOk()
            ->assertViewIs('sales-daily-plans.team-index')
            ->assertSee($sales->name)
            ->assertDontSee($otherSales->name);
    }

    /** @return array{0: Branch, 1: Team, 2: User, 3: User} */
    private function teamContext(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->create(['branch_id' => $branch->id]);
        $coordinator = $this->teamUser($branch, $team, 'koordinator_sales');
        $team->update(['coordinator_id' => $coordinator->id]);
        $sales = $this->teamUser($branch, $team, 'sales');

        return [$branch, $team, $sales, $coordinator];
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

    private function lead(Branch $branch, Team $team, User $sales, User $creator, string $id, string $date): Lead
    {
        return Lead::factory()->create([
            'id_lead' => $id,
            'tanggal_lead' => $date,
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $creator->id,
        ]);
    }

    private function agenda(Branch $branch, Team $team, User $sales, SalesDailyPlan $plan, int $duration, int $order, string $title): SalesAgenda
    {
        return SalesAgenda::query()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'daily_plan_id' => $plan->id,
            'created_by' => $sales->id,
            'owner_id' => $sales->id,
            'agenda_date' => $plan->plan_date->toDateString(),
            'activity_category' => 'Follow-up',
            'title' => $title,
            'plan_detail' => 'Detail '.$title,
            'duration_minutes' => $duration,
            'sort_order' => $order,
            'status' => 'planned',
        ]);
    }
}
