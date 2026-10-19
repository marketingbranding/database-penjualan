<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamNormalizationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class TeamNormalizationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $mappingPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->mappingPaths as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_dry_run_reports_the_plan_without_mutating_the_database(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario, [$scenario['unassigned']->id]);
        $before = [
            'teams' => Team::query()->count(),
            'memberships' => DB::table('team_user')->count(),
            'roles' => DB::table('model_has_roles')->count(),
        ];

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY_RUN');

        $this->assertSame($before['teams'], Team::query()->count());
        $this->assertSame($before['memberships'], DB::table('team_user')->count());
        $this->assertSame($before['roles'], DB::table('model_has_roles')->count());
        $this->assertDatabaseMissing('teams', ['code' => 'COORD-B01-U'.$scenario['coordinator']->id]);
    }

    public function test_apply_creates_final_team_memberships_and_roles_without_deactivating_legacy_team(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('APPLIED');

        $finalTeam = Team::query()->where('code', 'COORD-B01-U'.$scenario['coordinator']->id)->firstOrFail();
        $this->assertModelExists($finalTeam);
        $this->assertTrue($finalTeam->is_active);
        $this->assertSame($scenario['branch']->id, $finalTeam->branch_id);
        $this->assertSame($scenario['coordinator']->id, $finalTeam->coordinator_id);
        $this->assertNull($finalTeam->project_id);
        $this->assertNull($finalTeam->supervisor_id);
        $this->assertDatabaseHas('team_user', ['team_id' => $finalTeam->id, 'user_id' => $scenario['coordinator']->id, 'is_default' => true]);
        $this->assertDatabaseHas('team_user', ['team_id' => $finalTeam->id, 'user_id' => $scenario['sales']->id, 'is_default' => true]);
        $coordinatorRole = Role::query()->where('team_id', $finalTeam->id)->where('name', 'koordinator_sales')->firstOrFail();
        $salesRole = Role::query()->where('team_id', $finalTeam->id)->where('name', 'sales')->firstOrFail();
        $this->assertDatabaseHas('model_has_roles', ['team_id' => $finalTeam->id, 'role_id' => $coordinatorRole->id, 'model_id' => $scenario['coordinator']->id, 'model_type' => User::class]);
        $this->assertDatabaseHas('model_has_roles', ['team_id' => $finalTeam->id, 'role_id' => $salesRole->id, 'model_id' => $scenario['sales']->id, 'model_type' => User::class]);
        $this->assertDatabaseHas('teams', ['id' => $scenario['legacyTeam']->id, 'is_active' => true]);
        $this->assertDatabaseMissing('team_user', ['team_id' => $scenario['legacyTeam']->id, 'user_id' => $scenario['sales']->id]);
    }

    public function test_unassigned_active_sales_is_reported_and_left_in_legacy_team(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario, [$scenario['unassigned']->id]);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('unassigned_active_sales');

        $this->assertDatabaseHas('team_user', ['team_id' => $scenario['legacyTeam']->id, 'user_id' => $scenario['unassigned']->id]);
        $this->assertDatabaseMissing('teams', ['code' => 'COORD-B01-U'.$scenario['unassigned']->id]);
    }

    public function test_branch_mismatch_blocks_apply_before_creating_a_team(): void
    {
        $scenario = $this->scenario();
        $otherBranch = Branch::factory()->create(['code' => 'OTHER-BR']);
        $mapping = $this->mapping($scenario, [], ['branch_code' => $otherBranch->code]);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertFailed()
            ->expectsOutputToContain('coordinator_branch_mismatch');

        $this->assertDatabaseMissing('teams', ['code' => 'COORD-B02-U'.$scenario['coordinator']->id]);
    }

    public function test_sales_mapped_to_two_coordinators_is_rejected(): void
    {
        $scenario = $this->scenario();
        $secondCoordinator = $this->userWithTeamRole($scenario['branch'], $scenario['legacyTeam'], 'koordinator_sales', 'Second Coordinator');
        $mapping = $this->mapping($scenario, [], ['second_coordinator' => $secondCoordinator]);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertFailed()
            ->expectsOutputToContain('sales_assigned_to_multiple_coordinators');

        $this->assertDatabaseMissing('teams', ['code' => 'COORD-B01-U'.$secondCoordinator->id]);
    }

    public function test_legacy_teams_are_retained_and_not_deactivated_by_first_apply(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertSuccessful();

        $this->assertModelExists($scenario['legacyTeam']->fresh());
        $this->assertTrue((bool) $scenario['legacyTeam']->fresh()->is_active);
    }

    public function test_second_apply_reuses_the_same_final_team_and_memberships(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertSuccessful();
        $teamCount = Team::query()->count();
        $membershipCount = DB::table('team_user')->count();
        $roleCount = DB::table('model_has_roles')->count();

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('REUSE_FINAL_TEAM');

        $this->assertSame($teamCount, Team::query()->count());
        $this->assertSame($membershipCount, DB::table('team_user')->count());
        $this->assertSame($roleCount, DB::table('model_has_roles')->count());
    }

    public function test_lead_mismatch_is_reported_without_changing_lead_ownership(): void
    {
        $scenario = $this->scenario();
        $lead = Lead::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'sales_pic_id' => $scenario['sales']->id,
            'created_by' => $scenario['sales']->id,
        ]);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertSuccessful();

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'team_id' => $scenario['legacyTeam']->id]);
    }

    public function test_existing_final_team_is_reused_safely(): void
    {
        $scenario = $this->scenario();
        $existing = Team::factory()->for($scenario['branch'])->create([
            'code' => 'COORD-B01-U'.$scenario['coordinator']->id,
            'coordinator_id' => $scenario['coordinator']->id,
            'project_id' => null,
            'supervisor_id' => null,
        ]);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertSuccessful();

        $this->assertSame(1, Team::query()->where('code', $existing->code)->count());
        $this->assertDatabaseHas('team_user', ['team_id' => $existing->id, 'user_id' => $scenario['sales']->id]);
    }

    public function test_invalid_mapping_blocks_apply_without_mutation(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario);
        $data = json_decode((string) File::get($mapping), true, 512, JSON_THROW_ON_ERROR);
        unset($data['format_version']);
        File::put($mapping, json_encode($data, JSON_THROW_ON_ERROR));
        $before = Team::query()->count();

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertFailed()
            ->expectsOutputToContain('mapping_format_invalid');

        $this->assertSame($before, Team::query()->count());
    }

    public function test_reports_to_is_preserved_during_normalization(): void
    {
        $scenario = $this->scenario();
        $reportsTo = User::factory()->for($scenario['branch'])->create();
        $scenario['coordinator']->update(['reports_to_id' => $reportsTo->id]);
        $scenario['sales']->update(['reports_to_id' => $reportsTo->id]);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertSuccessful();

        $this->assertSame($reportsTo->id, $scenario['coordinator']->fresh()->reports_to_id);
        $this->assertSame($reportsTo->id, $scenario['sales']->fresh()->reports_to_id);
    }

    public function test_apply_failure_rolls_back_created_teams_and_memberships(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario);
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with(strtolower(trim($query->sql)), 'insert into') && str_contains($query->sql, 'team_user')) {
                $armed = false;
                throw new RuntimeException('forced normalization failure');
            }
        });
        $beforeTeams = Team::query()->count();
        $beforeMemberships = DB::table('team_user')->count();

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertFailed();

        $this->assertSame($beforeTeams, Team::query()->count());
        $this->assertSame($beforeMemberships, DB::table('team_user')->count());
        $this->assertDatabaseMissing('teams', ['code' => 'COORD-B01-U'.$scenario['coordinator']->id]);
    }

    public function test_current_coordinator_owned_agenda_keeps_owner_and_maps_to_coordinator_team(): void
    {
        $scenario = $this->scenario();
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'created_by' => $scenario['coordinator']->id,
            'owner_id' => $scenario['coordinator']->id,
            'agenda_date' => today(),
        ]);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('coordinator_owned_agendas_resolved');

        $finalTeam = Team::query()->where('code', 'COORD-B01-U'.$scenario['coordinator']->id)->firstOrFail();
        $agenda->refresh();

        $this->assertSame($scenario['coordinator']->id, $agenda->owner_id);
        $this->assertSame($finalTeam->id, $agenda->team_id);
        $this->assertDatabaseHas('teams', ['id' => $finalTeam->id, 'coordinator_id' => $scenario['coordinator']->id]);
    }

    public function test_coordinator_owned_agenda_with_several_sales_members_does_not_select_a_sales_owner(): void
    {
        $scenario = $this->scenario();
        $secondSales = $this->userWithTeamRole($scenario['branch'], $scenario['legacyTeam'], 'sales', 'Second Sales');
        $thirdSales = $this->userWithTeamRole($scenario['branch'], $scenario['legacyTeam'], 'sales', 'Third Sales');
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'created_by' => $scenario['coordinator']->id,
            'owner_id' => $scenario['coordinator']->id,
        ]);
        $mapping = $this->mapping($scenario, [], ['sales_users' => [$scenario['sales'], $secondSales, $thirdSales]]);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])->assertSuccessful();

        $agenda->refresh();

        $this->assertSame($scenario['coordinator']->id, $agenda->owner_id);
        $this->assertNotContains($agenda->owner_id, [$scenario['sales']->id, $secondSales->id, $thirdSales->id]);
    }

    public function test_coordinator_owned_agenda_without_a_mapped_final_team_is_a_blocker(): void
    {
        $scenario = $this->scenario();
        $unmappedCoordinator = $this->userWithTeamRole($scenario['branch'], $scenario['legacyTeam'], 'koordinator_sales', 'Unmapped Coordinator');
        SalesAgenda::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'created_by' => $unmappedCoordinator->id,
            'owner_id' => $unmappedCoordinator->id,
        ]);
        $mapping = $this->mapping($scenario);

        $report = app(TeamNormalizationService::class)->run($mapping);

        $this->assertSame(1, $report['agendas']['mismatch_needs_review']);
        $this->assertContains('agenda_mismatches_needs_review', array_column($report['readiness_blockers'], 'code'));
    }

    public function test_coordinator_owned_agenda_with_multiple_final_teams_is_blocked_without_selection(): void
    {
        $scenario = $this->scenario();
        $firstFinalTeam = Team::factory()->for($scenario['branch'])->create([
            'code' => 'COORD-B01-U'.$scenario['coordinator']->id,
            'coordinator_id' => $scenario['coordinator']->id,
            'project_id' => null,
            'supervisor_id' => null,
        ]);
        $secondFinalTeam = Team::factory()->for($scenario['branch'])->create([
            'code' => 'COORD-B01-U'.$scenario['coordinator']->id.'-SECOND',
            'coordinator_id' => $scenario['coordinator']->id,
            'project_id' => null,
            'supervisor_id' => null,
        ]);
        Rbac::provisionTeam($firstFinalTeam);
        Rbac::provisionTeam($secondFinalTeam);
        $firstFinalTeam->users()->attach($scenario['coordinator'], ['is_default' => true]);
        $secondFinalTeam->users()->attach($scenario['coordinator'], ['is_default' => false]);
        SalesAgenda::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'created_by' => $scenario['coordinator']->id,
            'owner_id' => $scenario['coordinator']->id,
        ]);
        $mapping = $this->mapping($scenario);

        $report = app(TeamNormalizationService::class)->run($mapping);

        $this->assertSame('BLOCKED', $report['status']);
        $this->assertSame('multiple_active_teams_for_coordinator', $report['preflight_errors'][0]['code']);
    }

    public function test_sales_owned_agenda_still_maps_to_the_sales_final_team(): void
    {
        $scenario = $this->scenario();
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'created_by' => $scenario['sales']->id,
            'owner_id' => $scenario['sales']->id,
        ]);
        $mapping = $this->mapping($scenario);

        app(TeamNormalizationService::class)->run($mapping, true);

        $agenda->refresh();
        $finalTeam = Team::query()->where('code', 'COORD-B01-U'.$scenario['coordinator']->id)->firstOrFail();

        $this->assertSame($scenario['sales']->id, $agenda->owner_id);
        $this->assertSame($finalTeam->id, $agenda->team_id);
    }

    public function test_historical_agenda_exception_remains_approved(): void
    {
        $scenario = $this->scenario();
        $unmappedCoordinator = $this->userWithTeamRole($scenario['branch'], $scenario['legacyTeam'], 'koordinator_sales', 'Historical Coordinator');
        SalesAgenda::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => null,
            'created_by' => $unmappedCoordinator->id,
            'owner_id' => $unmappedCoordinator->id,
            'agenda_date' => today()->subMonth(),
        ]);
        $mapping = $this->mapping($scenario);

        $report = app(TeamNormalizationService::class)->run($mapping);

        $this->assertSame(1, $report['agendas']['approved_historical_exception']);
        $this->assertSame(0, $report['agendas']['mismatch_needs_review']);
    }

    public function test_coordinator_owned_lead_remains_a_sales_ownership_blocker(): void
    {
        $scenario = $this->scenario();
        Lead::factory()->create([
            'branch_id' => $scenario['branch']->id,
            'team_id' => $scenario['legacyTeam']->id,
            'sales_pic_id' => $scenario['coordinator']->id,
            'created_by' => $scenario['coordinator']->id,
        ]);
        $mapping = $this->mapping($scenario);

        $report = app(TeamNormalizationService::class)->run($mapping);

        $this->assertSame(1, $report['leads']['mismatch_needs_review']);
        $this->assertContains('lead_mismatches_needs_review', array_column($report['readiness_blockers'], 'code'));
    }

    public function test_cutover_agenda_fixture_resolves_all_five_coordinator_owned_records(): void
    {
        $scenario = $this->scenario();
        $fixtureMap = [5520 => 5332, 5521 => 5333, 5523 => 5335, 5524 => 5336, 5525 => 5337];
        foreach ($fixtureMap as $sourceId => $targetId) {
            DB::table('sales_agendas')->insert([
                'id' => $targetId,
                'branch_id' => $scenario['branch']->id,
                'team_id' => $scenario['legacyTeam']->id,
                'created_by' => $scenario['coordinator']->id,
                'owner_id' => $scenario['coordinator']->id,
                'sales_case_id' => null,
                'lead_id' => null,
                'agenda_date' => '2026-10-28',
                'activity_category' => 'Administrasi',
                'title' => 'Cutover fixture '.$sourceId,
                'location' => null,
                'activity_result' => null,
                'status' => 'planned',
                'completed_at' => null,
                'created_at' => '2026-10-01 08:00:00',
                'updated_at' => '2026-10-01 08:00:00',
            ]);
        }
        $mapping = $this->mapping($scenario);

        $report = app(TeamNormalizationService::class)->run($mapping, true);
        $finalTeam = Team::query()->where('code', 'COORD-B01-U'.$scenario['coordinator']->id)->firstOrFail();

        $this->assertSame(5, $report['agendas']['coordinator_owned_resolved']);
        $this->assertSame(0, $report['agendas']['mismatch_needs_review']);
        $this->assertSame(5, $report['counts']['agenda_team_updates']);
        $this->assertSame(5, DB::table('sales_agendas')->whereIn('id', array_values($fixtureMap))->where('owner_id', $scenario['coordinator']->id)->where('team_id', $finalTeam->id)->count());
    }

    public function test_legacy_deactivation_requires_complete_final_coverage(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario, [$scenario['unassigned']->id]);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--deactivate-legacy' => true, '--apply' => true])
            ->assertFailed()
            ->expectsOutputToContain('legacy_deactivation_unassigned_active_sales');

        $this->assertDatabaseHas('teams', ['id' => $scenario['legacyTeam']->id, 'is_active' => true]);
    }

    public function test_existing_cross_branch_membership_blocks_apply(): void
    {
        $scenario = $this->scenario();
        $otherBranch = Branch::factory()->create(['code' => 'CROSS-BR']);
        $otherTeam = Team::factory()->for($otherBranch)->create(['code' => 'FINAL-CROSS', 'name' => 'Final Cross Team']);
        Rbac::provisionTeam($otherTeam);
        $otherTeam->users()->attach($scenario['sales'], ['is_default' => false]);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--apply' => true])
            ->assertFailed()
            ->expectsOutputToContain('existing_cross_branch_membership');

        $this->assertDatabaseMissing('teams', ['code' => 'COORD-B01-U'.$scenario['coordinator']->id]);
    }

    public function test_legacy_deactivation_requires_apply_flag(): void
    {
        $scenario = $this->scenario();
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:normalize-teams', ['--mapping' => $mapping, '--deactivate-legacy' => true])
            ->assertFailed()
            ->expectsOutputToContain('deactivation_requires_apply');

        $this->assertDatabaseHas('teams', ['id' => $scenario['legacyTeam']->id, 'is_active' => true]);
    }

    /** @return array{branch: Branch, legacyTeam: Team, coordinator: User, sales: User, unassigned: User} */
    private function scenario(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['code' => 'NORM-BR']);
        $legacyTeam = Team::factory()->for($branch)->create(['code' => 'SRC-TEAM-BRANCH-01', 'name' => 'Migrasi Tim Normalisasi']);
        Rbac::provisionTeam($legacyTeam);
        $coordinator = $this->userWithTeamRole($branch, $legacyTeam, 'koordinator_sales', 'Coordinator');
        $sales = $this->userWithTeamRole($branch, $legacyTeam, 'sales', 'Sales');
        $unassigned = $this->userWithTeamRole($branch, $legacyTeam, 'sales', 'Unassigned Sales');

        return compact('branch', 'legacyTeam', 'coordinator', 'sales', 'unassigned');
    }

    private function userWithTeamRole(Branch $branch, Team $team, string $roleName, string $name): User
    {
        $user = User::factory()->for($branch)->create(['name' => $name]);
        $team->users()->attach($user, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $user->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());

        return $user;
    }

    /** @param list<int> $unassignedIds
     * @param  array{branch_code?: string, second_coordinator?: User, sales_users?: list<User>}  $options
     */
    private function mapping(array $scenario, array $unassignedIds = [], array $options = []): string
    {
        if ($unassignedIds === []) {
            $unassignedIds = [$scenario['unassigned']->id];
        }

        $branchCode = $options['branch_code'] ?? $scenario['branch']->code;
        $salesUsers = $options['sales_users'] ?? [$scenario['sales']];
        $coordinators = [[
            'legacy_user_id' => 1,
            'next_user_id' => $scenario['coordinator']->id,
            'sales' => collect($salesUsers)->values()->map(fn (User $sales, int $index): array => ['legacy_user_id' => $index + 2, 'next_user_id' => $sales->id])->all(),
        ]];
        if (isset($options['second_coordinator'])) {
            $coordinators[] = [
                'legacy_user_id' => 3,
                'next_user_id' => $options['second_coordinator']->id,
                'sales' => [['legacy_user_id' => 2, 'next_user_id' => $scenario['sales']->id]],
            ];
        }

        $accounting = [
            'MAPPED_ACTIVE' => collect($salesUsers)->values()->map(fn (User $sales, int $index): array => ['legacy_user_id' => $index + 2, 'next_user_id' => $sales->id])->all(),
            'UNASSIGNED_ACTIVE' => array_map(fn (int $id): array => ['next_user_id' => $id], $unassignedIds),
            'INACTIVE' => [],
            'ANONYMIZED' => [],
            'LEGACY_ONLY' => [],
            'DUPLICATE_DATA_ISSUE' => [],
            'OTHER' => [],
        ];
        $path = storage_path('app/team-normalization-tests/'.uniqid('mapping-', true).'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'format_version' => 1,
            'sales_accounting' => $accounting,
            'branches' => [[
                'legacy_branch_id' => 1,
                'branch_code' => $branchCode,
                'branch_name' => $scenario['branch']->name,
                'coordinators' => $coordinators,
            ]],
        ], JSON_THROW_ON_ERROR));
        $this->mappingPaths[] = $path;

        return $path;
    }
}
