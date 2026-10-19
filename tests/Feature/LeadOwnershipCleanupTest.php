<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadCutoverDisposition;
use App\Models\LeadHandoff;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\LeadOwnershipCleanupService;
use App\Support\Rbac;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadOwnershipCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_repairs_valid_sales_branch_and_team_without_writing(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario, $scenario['legacyTeam'], $scenario['sales'], $scenario['sales'], [
            'created_at' => '2026-09-30 10:00:00',
            'updated_at' => '2026-09-30 10:00:00',
        ]);
        $before = $lead->fresh()->getAttributes();

        $report = app(LeadOwnershipCleanupService::class)->run(
            CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'),
            'Asia/Jakarta',
        );

        $this->assertSame(1, $report['counts']['valid_sales_repairable']);
        $this->assertSame(1, $report['counts']['deterministic_repairs']);
        $this->assertSame($before, $lead->fresh()->getAttributes());
        $this->assertDatabaseCount('lead_cutover_dispositions', 0);
    }

    public function test_confirmed_recovered_owner_is_applied_without_changing_creator_or_timestamps(): void
    {
        $scenario = $this->scenario();
        $createdAt = '2026-09-30 10:00:00';
        $lead = $this->lead($scenario, $scenario['legacyTeam'], $scenario['coordinator'], $scenario['coordinator'], [
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $lead->activities()->create([
            'user_id' => $scenario['sales']->id,
            'activity_type' => 'Follow Up',
            'description' => 'Historical Sales follow-up',
            'happened_at' => $createdAt,
        ]);
        LeadHandoff::factory()->create([
            'lead_id' => $lead->id,
            'requested_by' => $scenario['sales']->id,
        ]);

        app(LeadOwnershipCleanupService::class)->run(
            CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'),
            'Asia/Jakarta',
            true,
        );

        $updated = $lead->fresh();
        $this->assertSame($scenario['sales']->id, $updated->sales_pic_id);
        $this->assertSame($scenario['finalTeam']->id, $updated->team_id);
        $this->assertSame($scenario['coordinator']->id, $updated->created_by);
        $this->assertSame($createdAt, $updated->getRawOriginal('created_at'));
        $this->assertSame($createdAt, $updated->getRawOriginal('updated_at'));
    }

    public function test_high_confidence_owner_is_reported_but_not_auto_applied(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario, $scenario['legacyTeam'], $scenario['coordinator'], $scenario['coordinator']);
        $lead->activities()->create([
            'user_id' => $scenario['sales']->id,
            'activity_type' => 'Follow Up',
            'description' => 'Historical Sales follow-up',
            'happened_at' => '2026-09-30 10:00:00',
        ]);

        $report = app(LeadOwnershipCleanupService::class)->run(
            CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'),
            'Asia/Jakarta',
        );

        $this->assertSame(1, $report['counts']['high_confidence_recommendations']);
        $this->assertSame($scenario['coordinator']->id, $lead->fresh()->sales_pic_id);
    }

    public function test_current_month_unresolved_lead_is_preserved_for_manual_assignment(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario, $scenario['legacyTeam'], $scenario['coordinator'], $scenario['coordinator'], [
            'created_at' => '2026-10-01 00:30:00',
        ]);

        app(LeadOwnershipCleanupService::class)->run(
            CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'),
            'Asia/Jakarta',
            true,
        );

        $this->assertDatabaseHas('lead_cutover_dispositions', [
            'lead_id' => $lead->id,
            'classification' => LeadCutoverDisposition::NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH,
        ]);
        $this->assertNotNull(Lead::query()->find($lead->id));
    }

    public function test_old_unresolved_lead_is_excluded_from_operation_without_deleting_source_record(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario, $scenario['legacyTeam'], $scenario['coordinator'], $scenario['coordinator'], [
            'created_at' => '2026-09-30 16:59:00',
        ]);

        app(LeadOwnershipCleanupService::class)->run(
            CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta'),
            'Asia/Jakarta',
            true,
        );

        $this->assertDatabaseHas('lead_cutover_dispositions', [
            'lead_id' => $lead->id,
            'classification' => LeadCutoverDisposition::EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED,
        ]);
        $this->assertNull(Lead::query()->find($lead->id));
        $this->assertNotNull(Lead::withoutGlobalScopes()->find($lead->id));
    }

    public function test_cleanup_uses_timezone_aware_month_boundary_and_apply_is_idempotent(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario, $scenario['legacyTeam'], $scenario['coordinator'], $scenario['coordinator'], [
            'created_at' => '2026-09-30 17:30:00',
        ]);
        $service = app(LeadOwnershipCleanupService::class);
        $cutoverAt = CarbonImmutable::parse('2026-10-19 10:00:00', 'Asia/Jakarta');

        $first = $service->run($cutoverAt, 'Asia/Jakarta', true);
        $second = $service->run($cutoverAt, 'Asia/Jakarta', true);

        $this->assertSame('2026-10-01T00:00:00+07:00', $first['current_month_boundary']);
        $this->assertSame(1, $first['counts']['needs_manual_assignment_current_month']);
        $this->assertSame(0, $second['counts']['changed']);
        $this->assertSame(1, LeadCutoverDisposition::query()->where('lead_id', $lead->id)->count());
    }

    /** @return array{branch: Branch, finalTeam: Team, legacyTeam: Team, coordinator: User, sales: User} */
    private function scenario(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['code' => 'CLEANUP-BR']);
        $coordinator = User::factory()->for($branch, 'branch')->create();
        $finalTeam = Team::factory()->for($branch)->create([
            'code' => 'COORD-B01-U'.$coordinator->id,
            'coordinator_id' => $coordinator->id,
        ]);
        $this->assignTeamRole($coordinator, $finalTeam, 'koordinator_sales');
        $sales = User::factory()->for($branch, 'branch')->create();
        $this->assignTeamRole($sales, $finalTeam, 'sales');
        $legacyTeam = Team::factory()->for($branch)->create(['code' => 'SRC-CLEANUP']);

        return compact('branch', 'finalTeam', 'legacyTeam', 'coordinator', 'sales');
    }

    /** @param array{branch: Branch} $scenario */
    private function lead(array $scenario, Team $team, User $salesPic, User $creator, array $overrides = []): Lead
    {
        return Lead::factory()->create([
            'id_lead' => 'CLEANUP-'.(Lead::withoutGlobalScopes()->count() + 1),
            'branch_id' => $scenario['branch']->id,
            'team_id' => $team->id,
            'sales_pic_id' => $salesPic->id,
            'created_by' => $creator->id,
            ...$overrides,
        ]);
    }

    private function assignTeamRole(User $user, Team $team, string $roleName): void
    {
        Rbac::provisionTeam($team);
        $team->users()->syncWithoutDetaching([$user->id => ['is_default' => true]]);
        setPermissionsTeamId($team->id);
        $user->unsetRelation('roles')->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
