<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class LeadTeamReassignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_dry_run_reports_safe_lead_without_mutating_any_lead_field(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);
        $before = $lead->getAttributes();
        $activityCount = LeadActivity::query()->where('lead_id', $lead->id)->count();

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping))
            ->assertSuccessful()
            ->expectsOutputToContain('safe_reassign=1');

        $after = $lead->fresh()->getAttributes();
        foreach (array_keys($before) as $field) {
            $this->assertSame($before[$field], $after[$field], $field.' must remain unchanged during dry-run.');
        }
        $this->assertSame($activityCount, LeadActivity::query()->where('lead_id', $lead->id)->count());
    }

    public function test_apply_reassigns_only_team_id_and_preserves_creator_pic_status_history_and_timestamps(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator'], ['status_lead' => 'Diskusi']);
        $activity = LeadActivity::factory()->create(['lead_id' => $lead->id, 'user_id' => $scenario['sales']->id]);
        $mapping = $this->mapping($scenario);
        $before = $lead->fresh()->getAttributes();

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('changed=1');

        $after = $lead->fresh()->getAttributes();
        $this->assertSame($scenario['finalTeam']->id, $after['team_id']);
        foreach (array_keys($before) as $field) {
            if ($field !== 'team_id') {
                $this->assertSame($before[$field], $after[$field], $field.' must remain unchanged.');
            }
        }
        $this->assertModelExists($activity->fresh());
        $this->assertSame(1, LeadActivity::query()->where('lead_id', $lead->id)->count());
    }

    public function test_coordinator_created_lead_follows_sales_pic_team_not_creator_team(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))->assertSuccessful();

        $this->assertSame($scenario['sales']->id, $lead->fresh()->sales_pic_id);
        $this->assertSame($scenario['coordinator']->id, $lead->fresh()->created_by);
        $this->assertSame($scenario['finalTeam']->id, $lead->fresh()->team_id);
    }

    public function test_existing_consistent_lead_is_not_updated(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['finalTeam'], $scenario['sales'], $scenario['sales']);
        $mapping = $this->mapping($scenario);
        $before = $lead->fresh()->getAttributes();

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('changed=0');

        $this->assertSame($before, $lead->fresh()->getAttributes());
    }

    public function test_unassigned_active_sales_lead_is_never_reassigned(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['unassignedSales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('unassigned_active_sales');

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
        $this->assertDatabaseHas('teams', ['id' => $scenario['legacyTeam']->id, 'is_active' => true]);
    }

    public function test_inactive_sales_lead_is_reported_and_preserved(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['inactiveSales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('inactive_sales');

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
        $this->assertDatabaseHas('teams', ['id' => $scenario['legacyTeam']->id, 'is_active' => true]);
    }

    public function test_lead_branch_conflict_is_never_reassigned(): void
    {
        $scenario = $this->scenario();
        $otherBranch = Branch::factory()->create(['code' => 'OTHER-BR']);
        $lead = $this->lead($otherBranch, $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('lead_branch_team_mismatch');

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
    }

    public function test_multiple_final_teams_for_one_sales_blocks_that_lead_only(): void
    {
        $scenario = $this->scenario(withSecondTeam: true);
        $this->assignTeamRole($scenario['sales'], $scenario['secondFinalTeam'], 'sales');
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario, includeSecondSalesTeam: true);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('sales_has_multiple_final_teams=1');

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
    }

    public function test_sales_pic_without_final_team_is_reported_for_legacy_coordinator_lead(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['coordinator'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful()
            ->expectsOutputToContain('sales_pic_has_no_final_team');

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
    }

    public function test_apply_is_idempotent_after_safe_leads_are_reassigned(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))->assertSuccessful();
        $first = $lead->fresh()->getAttributes();

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertSuccessful();

        $this->assertSame($first, $lead->fresh()->getAttributes());
    }

    public function test_legacy_teams_are_not_deleted_or_deactivated(): void
    {
        $scenario = $this->scenario();
        $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))->assertSuccessful();

        $this->assertDatabaseHas('teams', [
            'id' => $scenario['legacyTeam']->id,
            'code' => 'SRC-TEAM-BRANCH-01',
            'is_active' => true,
        ]);
    }

    public function test_transaction_rolls_back_all_lead_team_updates_on_failure(): void
    {
        $scenario = $this->scenario();
        $firstLead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $secondLead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);
        $updateCount = 0;
        DB::listen(function (object $query) use (&$updateCount): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'update') && str_contains($query->sql, '"leads"')) {
                $updateCount++;
                if ($updateCount === 2) {
                    throw new RuntimeException('forced lead reassignment failure');
                }
            }
        });

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))->assertFailed();

        $this->assertSame($scenario['legacyTeam']->id, $firstLead->fresh()->team_id);
        $this->assertSame($scenario['legacyTeam']->id, $secondLead->fresh()->team_id);
    }

    public function test_invalid_mapping_blocks_apply_without_mutating_leads(): void
    {
        $scenario = $this->scenario();
        $lead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator']);
        $mapping = $this->mapping($scenario);
        $data = json_decode((string) File::get($mapping), true, 512, JSON_THROW_ON_ERROR);
        $data['format_version'] = 99;
        File::put($mapping, json_encode($data, JSON_THROW_ON_ERROR));

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))
            ->assertFailed()
            ->expectsOutputToContain('mapping_format_invalid');

        $this->assertSame($scenario['legacyTeam']->id, $lead->fresh()->team_id);
    }

    public function test_sales_and_coordinator_dashboards_use_the_reassigned_team_scope(): void
    {
        $scenario = $this->scenario(withSecondTeam: true);
        $firstLead = $this->lead($scenario['branch'], $scenario['legacyTeam'], $scenario['sales'], $scenario['coordinator'], ['nama_konsumen' => 'First Team Lead']);
        $secondLead = $this->lead($scenario['branch'], $scenario['secondLegacyTeam'], $scenario['secondSales'], $scenario['secondCoordinator'], ['nama_konsumen' => 'Second Team Lead']);
        $mapping = $this->mapping($scenario, includeSecondSalesTeam: true);

        $this->artisan('oasis:reassign-lead-teams', $this->commandArguments($mapping, true))->assertSuccessful();

        $this->actingAs($scenario['sales'])->withSession(['active_team_id' => $scenario['finalTeam']->id])
            ->get(route('leads.index'))
            ->assertSee($firstLead->nama_konsumen)
            ->assertDontSee($secondLead->nama_konsumen);
        $this->actingAs($scenario['coordinator'])->withSession(['active_team_id' => $scenario['finalTeam']->id])
            ->get(route('leads.index'))
            ->assertSee($firstLead->nama_konsumen)
            ->assertDontSee($secondLead->nama_konsumen);
    }

    public function test_lead_pic_change_keeps_the_lead_in_the_new_sales_team(): void
    {
        $scenario = $this->scenario();
        $newSales = User::factory()->for($scenario['branch'], 'branch')->create(['name' => 'Replacement Sales']);
        $this->assignTeamRole($newSales, $scenario['finalTeam'], 'sales');
        $lead = $this->lead($scenario['branch'], $scenario['finalTeam'], $scenario['sales'], $scenario['coordinator']);

        $this->actingAs($scenario['coordinator'])
            ->withSession(['active_team_id' => $scenario['finalTeam']->id])
            ->put(route('leads.update', $lead), [
                'tanggal_lead' => $lead->tanggal_lead->toDateString(),
                'sumber_lead' => $lead->sumber_lead,
                'kanal_masuk' => $lead->kanal_masuk,
                'aktivitas_lead' => $lead->aktivitas_lead,
                'nama_konsumen' => $lead->nama_konsumen,
                'no_hp' => $lead->no_hp,
                'project_id' => null,
                'sales_pic_id' => $newSales->id,
                'status_lead' => $lead->status_lead,
            ])
            ->assertRedirect(route('leads.show', $lead));

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'sales_pic_id' => $newSales->id,
            'team_id' => $scenario['finalTeam']->id,
        ]);
    }

    /** @return array{branch: Branch, finalTeam: Team, legacyTeam: Team, coordinator: User, sales: User, unassignedSales: User, inactiveSales: User, secondFinalTeam?: Team, secondLegacyTeam?: Team, secondCoordinator?: User, secondSales?: User} */
    private function scenario(bool $withSecondTeam = false): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['code' => 'LEAD-BR']);
        $coordinator = User::factory()->for($branch, 'branch')->create(['name' => 'Coordinator One']);
        $finalTeam = Team::factory()->for($branch)->create([
            'code' => 'COORD-B01-U'.$coordinator->id,
            'name' => 'Final Team One',
            'project_id' => null,
            'coordinator_id' => $coordinator->id,
        ]);
        $this->assignTeamRole($coordinator, $finalTeam, 'koordinator_sales');
        $sales = User::factory()->for($branch, 'branch')->create(['name' => 'Mapped Sales']);
        $this->assignTeamRole($sales, $finalTeam, 'sales');

        $legacyTeam = Team::factory()->for($branch)->create([
            'code' => 'SRC-TEAM-BRANCH-01',
            'name' => 'Migrasi Tim LEAD-BR',
            'coordinator_id' => null,
        ]);
        Rbac::provisionTeam($legacyTeam);
        $this->assignTeamRole($sales, $legacyTeam, 'sales');

        $unassignedSales = User::factory()->for($branch, 'branch')->create(['name' => 'Unassigned Sales']);
        $this->assignTeamRole($unassignedSales, $legacyTeam, 'sales');
        $inactiveSales = User::factory()->for($branch, 'branch')->create(['name' => 'Inactive Sales']);
        $this->assignTeamRole($inactiveSales, $legacyTeam, 'sales');

        $scenario = compact('branch', 'finalTeam', 'legacyTeam', 'coordinator', 'sales', 'unassignedSales', 'inactiveSales');
        if (! $withSecondTeam) {
            return $scenario;
        }

        $secondCoordinator = User::factory()->for($branch, 'branch')->create(['name' => 'Coordinator Two']);
        $secondFinalTeam = Team::factory()->for($branch)->create([
            'code' => 'COORD-B01-U'.$secondCoordinator->id,
            'name' => 'Final Team Two',
            'project_id' => null,
            'coordinator_id' => $secondCoordinator->id,
        ]);
        $this->assignTeamRole($secondCoordinator, $secondFinalTeam, 'koordinator_sales');
        $secondSales = User::factory()->for($branch, 'branch')->create(['name' => 'Second Sales']);
        $this->assignTeamRole($secondSales, $secondFinalTeam, 'sales');
        $secondLegacyTeam = Team::factory()->for($branch)->create([
            'code' => 'SRC-TEAM-BRANCH-02',
            'name' => 'Migrasi Tim LEAD-BR-2',
            'coordinator_id' => null,
        ]);
        Rbac::provisionTeam($secondLegacyTeam);
        $this->assignTeamRole($secondSales, $secondLegacyTeam, 'sales');

        return [...$scenario, 'secondFinalTeam' => $secondFinalTeam, 'secondLegacyTeam' => $secondLegacyTeam, 'secondCoordinator' => $secondCoordinator, 'secondSales' => $secondSales];
    }

    private function assignTeamRole(User $user, Team $team, string $roleName): void
    {
        Rbac::provisionTeam($team);
        $user->teams()->syncWithoutDetaching([$team->id => ['is_default' => true]]);
        setPermissionsTeamId($team->id);
        $user->unsetRelation('roles')->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** @param array<string, mixed> $overrides */
    private function lead(Branch $branch, Team $team, User $salesPic, User $creator, array $overrides = []): Lead
    {
        return Lead::factory()->create([
            'id_lead' => 'LEAD-TEST-'.(Lead::query()->count() + 1),
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $salesPic->id,
            'created_by' => $creator->id,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $scenario
     */
    private function mapping(array $scenario, bool $includeSecondSalesTeam = false): string
    {
        $mapped = [['next_user_id' => $scenario['sales']->id]];
        if ($includeSecondSalesTeam) {
            $mapped[] = ['next_user_id' => $scenario['secondSales']->id];
        }

        $path = storage_path('app/lead-team-reassignment-tests/'.uniqid('mapping-', true).'.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'format_version' => 1,
            'sales_accounting' => [
                'MAPPED_ACTIVE' => $mapped,
                'UNASSIGNED_ACTIVE' => [['next_user_id' => $scenario['unassignedSales']->id]],
                'INACTIVE' => [['next_user_id' => $scenario['inactiveSales']->id]],
                'ANONYMIZED' => [],
                'LEGACY_ONLY' => [],
                'DUPLICATE_DATA_ISSUE' => [],
                'OTHER' => [],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->temporaryPaths[] = $path;

        return $path;
    }

    /** @return array<string, mixed> */
    private function commandArguments(string $mapping, bool $apply = false): array
    {
        $reportPath = storage_path('app/lead-team-reassignment-tests/'.uniqid('report-', true).'.json');
        $this->temporaryPaths[] = $reportPath;

        return [
            '--mapping' => $mapping,
            '--report' => $reportPath,
            '--apply' => $apply,
        ];
    }
}
