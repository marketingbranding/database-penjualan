<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class TeamNormalizationService
{
    private const FORMAT_VERSION = 1;

    /** @var list<string> */
    private const SALES_CATEGORIES = [
        'MAPPED_ACTIVE',
        'UNASSIGNED_ACTIVE',
        'INACTIVE',
        'ANONYMIZED',
        'LEGACY_ONLY',
        'DUPLICATE_DATA_ISSUE',
        'OTHER',
    ];

    /**
     * Build or apply a deterministic Coordinator-Team normalization plan.
     *
     * @return array<string, mixed>
     */
    public function run(string $mappingPath, bool $apply = false, bool $deactivateLegacy = false): array
    {
        $report = $this->emptyReport($mappingPath, $apply, $deactivateLegacy);
        $absolutePath = $this->absolutePath($mappingPath);

        if (! File::exists($absolutePath)) {
            $this->addPreflightError($report, 'mapping_missing', 'Mapping file does not exist.', ['path' => $absolutePath]);

            return $report;
        }

        $report['mapping_sha256'] = hash_file('sha256', $absolutePath) ?: null;

        try {
            $mapping = $this->decodeMapping($absolutePath);
            $context = $this->preflight($mapping, $report);
            $report = $context['report'];

            if ($deactivateLegacy && ! $apply) {
                $this->addPreflightError($report, 'deactivation_requires_apply', 'Legacy Team deactivation requires --apply.');
            }

            if ($report['preflight_errors'] !== []) {
                $report['status'] = 'BLOCKED';

                return $report;
            }

            if (! $apply) {
                $report['status'] = 'DRY_RUN';

                return $report;
            }

            if ($deactivateLegacy && $context['deactivation_blockers'] !== []) {
                foreach ($context['deactivation_blockers'] as $blocker) {
                    $this->addPreflightError($report, $blocker['code'], $blocker['message'], $blocker['details'] ?? []);
                }

                $report['status'] = 'BLOCKED';

                return $report;
            }

            $report = DB::transaction(function () use ($context, $report, $deactivateLegacy): array {
                return $this->applyPlan($context, $report, $deactivateLegacy);
            });
            $report['status'] = 'APPLIED';

            return $report;
        } catch (Throwable $exception) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $report['status'] = 'FAILED';
            $report['transaction']['rolled_back'] = $apply;
            $report['errors'][] = [
                'code' => 'normalization_failed',
                'message' => $exception->getMessage(),
            ];

            report($exception);

            return $report;
        }
    }

    /** @return array<string, mixed> */
    private function emptyReport(string $mappingPath, bool $apply, bool $deactivateLegacy): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'deactivate_legacy' => $deactivateLegacy,
            'mapping_path' => $mappingPath,
            'mapping_sha256' => null,
            'status' => 'PREFLIGHT',
            'preflight_errors' => [],
            'errors' => [],
            'readiness_blockers' => [],
            'counts' => [
                'final_teams_proposed' => 0,
                'coordinators_mapped' => 0,
                'sales_role_users' => 0,
                'sales_mapped_active' => 0,
                'sales_unassigned_active' => 0,
                'sales_inactive' => 0,
                'sales_anonymized' => 0,
                'sales_legacy_only' => 0,
                'sales_duplicate_data_issue' => 0,
                'sales_other' => 0,
                'legacy_teams_retained' => 0,
                'legacy_teams_deactivated' => 0,
                'coordinator_owned_agendas_resolved' => 0,
                'agenda_team_updates' => 0,
            ],
            'sales_accounting' => [],
            'branches' => [],
            'coordinators' => [],
            'sales' => [],
            'legacy_teams' => [],
            'leads' => [],
            'agendas' => [],
            'sales_cases' => [],
            'transaction' => [
                'applied' => false,
                'rolled_back' => false,
                'created_team_ids' => [],
                'agenda_team_updates' => [],
                'old_team_memberships' => [],
                'old_team_roles' => [],
                'old_team_states' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function decodeMapping(string $path): array
    {
        $decoded = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Mapping file must contain a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $report
     * @return array{report: array<string, mixed>, final_plans: list<array<string, mixed>>, mapped_sales: array<int, int>, mapped_users: list<int>, legacy_team_ids: list<int>, deactivation_blockers: list<array<string, mixed>>}
     */
    private function preflight(array $mapping, array $report): array
    {
        $this->validateMappingShape($mapping, $report);

        $branches = Branch::query()->get()->groupBy('code');
        $users = User::query()->get()->keyBy('id');
        $rolesByUser = $this->rolesByUser();
        $allSalesIds = $rolesByUser->filter(fn (array $roles): bool => in_array('sales', $roles, true))->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $accounting = $this->salesAccounting($mapping, $report);
        $mappedSales = [];
        $mappedUsers = [];
        $finalPlans = [];
        $coordinatorIds = [];
        $generatedCodes = [];
        $branchRows = [];

        foreach ($mapping['branches'] ?? [] as $branchEntry) {
            if (! is_array($branchEntry)) {
                continue;
            }

            $branchCode = (string) ($branchEntry['branch_code'] ?? '');
            $branchMatches = $branches->get($branchCode, collect());
            if ($branchMatches->count() !== 1) {
                $this->addPreflightError($report, 'branch_mapping_not_deterministic', 'Branch code does not resolve to exactly one OASIS Next Branch.', ['branch_code' => $branchCode, 'matches' => $branchMatches->count()]);

                continue;
            }

            /** @var Branch $branch */
            $branch = $branchMatches->first();
            $branchRows[] = ['id' => $branch->id, 'code' => $branch->code, 'name' => $branch->name, 'legacy_branch_id' => $branchEntry['legacy_branch_id'] ?? null];

            foreach ($branchEntry['coordinators'] ?? [] as $coordinatorEntry) {
                if (! is_array($coordinatorEntry)) {
                    $this->addPreflightError($report, 'invalid_coordinator_entry', 'Coordinator mapping entry must be an object.');

                    continue;
                }

                $coordinatorId = (int) ($coordinatorEntry['next_user_id'] ?? 0);
                if ($coordinatorId < 1 || in_array($coordinatorId, $coordinatorIds, true)) {
                    $this->addPreflightError($report, 'duplicate_or_missing_coordinator', 'Coordinator mapping must contain unique positive OASIS Next user IDs.', ['user_id' => $coordinatorId]);

                    continue;
                }

                $coordinatorIds[] = $coordinatorId;
                $coordinator = $users->get($coordinatorId);
                if (! $coordinator instanceof User) {
                    $this->addPreflightError($report, 'coordinator_missing', 'Mapped Coordinator does not exist in OASIS Next.', ['user_id' => $coordinatorId]);

                    continue;
                }
                if ((int) $coordinator->branch_id !== (int) $branch->id) {
                    $this->addPreflightError($report, 'coordinator_branch_mismatch', 'Mapped Coordinator branch does not match the mapped Branch.', ['user_id' => $coordinatorId, 'branch_id' => $branch->id]);
                }
                if (! in_array('koordinator_sales', $rolesByUser->get($coordinatorId, []), true)) {
                    $this->addPreflightError($report, 'coordinator_role_missing', 'Mapped Coordinator does not have the expected Team role.', ['user_id' => $coordinatorId, 'role' => 'koordinator_sales']);
                }

                $salesEntries = is_array($coordinatorEntry['sales'] ?? null) ? $coordinatorEntry['sales'] : [];
                $salesIds = [];
                foreach ($salesEntries as $salesEntry) {
                    if (! is_array($salesEntry)) {
                        $this->addPreflightError($report, 'invalid_sales_entry', 'Sales mapping entry must be an object.', ['coordinator_id' => $coordinatorId]);

                        continue;
                    }

                    $salesId = (int) ($salesEntry['next_user_id'] ?? 0);
                    if ($salesId < 1 || in_array($salesId, $salesIds, true) || in_array($salesId, $mappedSales, true)) {
                        $this->addPreflightError($report, 'sales_assigned_to_multiple_coordinators', 'A Sales user must map to only one Coordinator.', ['sales_user_id' => $salesId]);

                        continue;
                    }

                    $salesIds[] = $salesId;
                    $mappedSales[] = $salesId;
                    $mappedUsers[] = $salesId;
                    $salesUser = $users->get($salesId);
                    if (! $salesUser instanceof User) {
                        $this->addPreflightError($report, 'sales_missing', 'Mapped Sales user does not exist in OASIS Next.', ['user_id' => $salesId]);

                        continue;
                    }
                    if ((int) $salesUser->branch_id !== (int) $branch->id) {
                        $this->addPreflightError($report, 'sales_branch_mismatch', 'Mapped Sales branch does not match the Coordinator Branch.', ['user_id' => $salesId, 'coordinator_id' => $coordinatorId, 'branch_id' => $branch->id]);
                    }
                    if (! in_array('sales', $rolesByUser->get($salesId, []), true)) {
                        $this->addPreflightError($report, 'sales_role_missing', 'Mapped Sales user does not have the expected Team role.', ['user_id' => $salesId, 'role' => 'sales']);
                    }
                }

                $mappedUsers[] = $coordinatorId;
                $code = $this->teamCode((int) $branch->id, $coordinatorId);
                if (in_array($code, $generatedCodes, true)) {
                    $this->addPreflightError($report, 'duplicate_final_team', 'The mapping would generate a duplicate final Team code.', ['code' => $code]);
                }
                $generatedCodes[] = $code;
                $finalPlans[] = $this->finalTeamPlan($branch, $coordinator, $salesIds, $code, $report);
            }
        }

        $mappedUsers = array_values(array_unique($mappedUsers));
        sort($mappedSales);
        sort($mappedUsers);
        $this->validateSalesAccounting($allSalesIds, $mappedSales, $accounting, $report);

        $legacyTeams = Team::query()->orderBy('id')->get()->filter(fn (Team $team): bool => $this->isLegacyTeam($team));
        $legacyTeamIds = $legacyTeams->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $teamRows = $this->teamRows();
        $this->validateExistingMemberships($finalPlans, $mappedUsers, $teamRows, $users, $report);

        $report['counts']['final_teams_proposed'] = count($finalPlans);
        $report['counts']['coordinators_mapped'] = count($coordinatorIds);
        $report['counts']['sales_role_users'] = count($allSalesIds);
        foreach (self::SALES_CATEGORIES as $category) {
            $countKey = match ($category) {
                'MAPPED_ACTIVE' => 'sales_mapped_active',
                'UNASSIGNED_ACTIVE' => 'sales_unassigned_active',
                'INACTIVE' => 'sales_inactive',
                'ANONYMIZED' => 'sales_anonymized',
                'LEGACY_ONLY' => 'sales_legacy_only',
                'DUPLICATE_DATA_ISSUE' => 'sales_duplicate_data_issue',
                default => 'sales_other',
            };
            $report['counts'][$countKey] = count($accounting[$category]);
        }
        $report['counts']['legacy_teams_retained'] = count($legacyTeamIds);
        $report['branches'] = $branchRows;
        $report['coordinators'] = array_map(fn (array $plan): array => $this->coordinatorReport($plan, $teamRows), $finalPlans);
        $report['sales'] = $this->salesReport($accounting, $mappedSales, $finalPlans, $teamRows, $users);
        $report['sales_accounting'] = $this->accountingReport($accounting);
        $report['legacy_teams'] = $this->legacyTeamReport($legacyTeams, $teamRows, $mappedUsers);
        $report['leads'] = $this->leadReport($finalPlans, $teamRows, $users);
        $report['agendas'] = $this->agendaReport($finalPlans, $teamRows, $users, $rolesByUser);
        $report['counts']['coordinator_owned_agendas_resolved'] = $report['agendas']['coordinator_owned_resolved'] ?? 0;
        $report['sales_cases'] = $this->salesCaseReport();
        $report['readiness_blockers'] = $this->readinessBlockers($report);

        $deactivationBlockers = $this->deactivationBlockers($report, $legacyTeams, $teamRows, $mappedUsers);
        if ($deactivationBlockers !== []) {
            $report['deactivation_blockers'] = $deactivationBlockers;
        }

        return [
            'report' => $report,
            'final_plans' => $finalPlans,
            'mapped_sales' => array_fill_keys($mappedSales, null),
            'mapped_users' => $mappedUsers,
            'legacy_team_ids' => $legacyTeamIds,
            'deactivation_blockers' => $deactivationBlockers,
        ];
    }

    /** @param array<string, mixed> $mapping
     * @param  array<string, mixed>  $report
     */
    private function validateMappingShape(array $mapping, array &$report): void
    {
        if ((int) ($mapping['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            $this->addPreflightError($report, 'mapping_format_invalid', 'Mapping format_version is missing or unsupported.', ['expected' => self::FORMAT_VERSION]);
        }
        if (! is_array($mapping['branches'] ?? null) || $mapping['branches'] === []) {
            $this->addPreflightError($report, 'mapping_branches_missing', 'Mapping must contain a non-empty branches array.');
        }
        if (! is_array($mapping['sales_accounting'] ?? null)) {
            $this->addPreflightError($report, 'sales_accounting_missing', 'Mapping must explain every Sales-role user through sales_accounting.');
        }
    }

    /** @return Collection<int, list<string>> */
    private function rolesByUser(): Collection
    {
        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', User::class)
            ->select(['mhr.model_id', 'r.name'])
            ->get()
            ->groupBy('model_id')
            ->map(fn ($roles): array => $roles->pluck('name')->unique()->values()->all());
    }

    /** @param array<string, mixed> $mapping
     * @param  array<string, mixed>  $report
     * @return array<string, list<array<string, int>>>
     */
    private function salesAccounting(array $mapping, array &$report): array
    {
        $accounting = [];
        foreach (self::SALES_CATEGORIES as $category) {
            $entries = $mapping['sales_accounting'][$category] ?? [];
            if (! is_array($entries)) {
                $this->addPreflightError($report, 'sales_accounting_category_invalid', 'Sales accounting category must be an array.', ['category' => $category]);
                $entries = [];
            }

            $accounting[$category] = [];
            foreach ($entries as $entry) {
                $id = is_array($entry) ? (int) ($entry['next_user_id'] ?? 0) : (int) $entry;
                if ($id < 1) {
                    $this->addPreflightError($report, 'sales_accounting_user_invalid', 'Sales accounting contains an invalid OASIS Next user ID.', ['category' => $category]);

                    continue;
                }

                $accounting[$category][] = ['next_user_id' => $id, 'legacy_user_id' => is_array($entry) ? ($entry['legacy_user_id'] ?? null) : null];
            }
        }

        return $accounting;
    }

    /** @param list<int> $allSalesIds
     * @param  list<int>  $mappedSales
     * @param  array<string, list<array<string, int>>>  $accounting
     * @param  array<string, mixed>  $report
     */
    private function validateSalesAccounting(array $allSalesIds, array $mappedSales, array $accounting, array &$report): void
    {
        $accounted = [];
        foreach ($accounting as $category => $entries) {
            foreach ($entries as $entry) {
                $id = $entry['next_user_id'];
                if (in_array($id, $accounted, true)) {
                    $this->addPreflightError($report, 'sales_accounting_duplicate', 'A Sales user appears in more than one accounting category.', ['user_id' => $id]);
                }
                $accounted[] = $id;
            }
        }

        sort($accounted);
        $expected = $allSalesIds;
        sort($expected);
        if ($accounted !== $expected) {
            $this->addPreflightError($report, 'sales_accounting_not_reconciled', 'Sales accounting does not reconcile exactly to OASIS Next Sales-role users.', ['missing' => array_values(array_diff($expected, $accounted)), 'unexpected' => array_values(array_diff($accounted, $expected))]);
        }

        $accountedMapped = array_map(fn (array $entry): int => $entry['next_user_id'], $accounting['MAPPED_ACTIVE']);
        sort($accountedMapped);
        if ($accountedMapped !== $mappedSales) {
            $this->addPreflightError($report, 'mapped_sales_accounting_mismatch', 'MAPPED_ACTIVE accounting does not match the authoritative Coordinator → Sales mapping.', ['accounting' => $accountedMapped, 'mapping' => $mappedSales]);
        }

        foreach (['LEGACY_ONLY', 'DUPLICATE_DATA_ISSUE', 'OTHER', 'ANONYMIZED'] as $category) {
            if ($accounting[$category] !== []) {
                $this->addPreflightError($report, 'unresolved_sales_category', 'Unresolved Sales accounting category blocks normalization.', ['category' => $category, 'user_ids' => array_map(fn (array $entry): int => $entry['next_user_id'], $accounting[$category])]);
            }
        }
    }

    /** @param array<string, mixed> $report */
    private function finalTeamPlan(Branch $branch, User $coordinator, array $salesIds, string $code, array &$report): array
    {
        $activeOwned = Team::query()->where('coordinator_id', $coordinator->id)->where('is_active', true)->get()->reject(fn (Team $team): bool => $this->isLegacyTeam($team));
        if ($activeOwned->count() > 1) {
            $this->addPreflightError($report, 'multiple_active_teams_for_coordinator', 'Coordinator already owns multiple active Teams.', ['coordinator_id' => $coordinator->id, 'team_ids' => $activeOwned->pluck('id')->all()]);
        }

        $existing = $activeOwned->first();
        if ($existing instanceof Team && (int) $existing->branch_id !== (int) $branch->id) {
            $this->addPreflightError($report, 'existing_team_branch_conflict', 'Existing active Coordinator Team has a different Branch.', ['coordinator_id' => $coordinator->id, 'team_id' => $existing->id, 'branch_id' => $branch->id]);
        }

        $codeTeam = Team::query()->where('code', $code)->first();
        if ($codeTeam instanceof Team && (! $codeTeam->is_active || ($existing instanceof Team && $codeTeam->id !== $existing->id) || ($existing === null && (int) $codeTeam->coordinator_id !== (int) $coordinator->id))) {
            $this->addPreflightError($report, 'final_team_code_conflict', 'Stable final Team code is already used by a conflicting Team.', ['code' => $code, 'team_id' => $codeTeam->id]);
        }

        $finalTeam = $existing ?? ($codeTeam instanceof Team && (int) $codeTeam->coordinator_id === (int) $coordinator->id ? $codeTeam : null);

        return [
            'branch_id' => (int) $branch->id,
            'branch_code' => (string) $branch->code,
            'coordinator_id' => (int) $coordinator->id,
            'coordinator_name' => (string) $coordinator->name,
            'sales_ids' => array_values(array_map('intval', $salesIds)),
            'code' => $code,
            'name' => 'Tim Koordinator '.(string) $coordinator->name,
            'team_id' => $finalTeam?->id,
            'action' => $finalTeam instanceof Team ? 'REUSE_FINAL_TEAM' : 'CREATE_FINAL_TEAM',
            'project_id' => $finalTeam?->project_id,
            'supervisor_id' => $finalTeam?->supervisor_id,
        ];
    }

    /** @return EloquentCollection<int, Team> */
    private function teamRows(): EloquentCollection
    {
        return Team::query()->with('users')->get();
    }

    /** @param list<array<string, mixed>> $finalPlans
     * @param  list<int>  $mappedUsers
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, User>  $users
     * @param  array<string, mixed>  $report
     */
    private function validateExistingMemberships(array $finalPlans, array $mappedUsers, EloquentCollection $teams, EloquentCollection $users, array &$report): void
    {
        $expectedTeams = [];
        foreach ($finalPlans as $plan) {
            $expectedTeams[$plan['coordinator_id']] = $plan['team_id'];
            foreach ($plan['sales_ids'] as $salesId) {
                $expectedTeams[$salesId] = $plan['team_id'];
            }
        }

        $mappedUserIds = array_values(array_unique($mappedUsers));
        foreach ($mappedUserIds as $userId) {
            $user = $users->firstWhere('id', $userId);
            if (! $user instanceof User) {
                continue;
            }

            foreach ($user->teams->where('is_active', true) as $team) {
                if ((int) $team->branch_id !== (int) $user->branch_id) {
                    $this->addPreflightError($report, 'existing_cross_branch_membership', 'A mapped user already has an active cross-Branch Team membership.', ['user_id' => $userId, 'team_id' => $team->id]);
                }

                if (! $this->isLegacyTeam($team) && ($expectedTeams[$userId] ?? null) !== $team->id) {
                    $this->addPreflightError($report, 'existing_non_legacy_team_conflict', 'A mapped user already belongs to a conflicting active non-legacy Team.', ['user_id' => $userId, 'team_id' => $team->id]);
                }
            }
        }
    }

    /** @param EloquentCollection<int, Team> $teams */
    private function coordinatorReport(array $plan, EloquentCollection $teams): array
    {
        $team = $plan['team_id'] !== null ? $teams->firstWhere('id', $plan['team_id']) : null;

        return [
            'coordinator_id' => $plan['coordinator_id'],
            'branch_id' => $plan['branch_id'],
            'sales_ids' => $plan['sales_ids'],
            'previous_team_ids' => $this->legacyTeamIdsForUsers(array_merge([$plan['coordinator_id']], $plan['sales_ids']), $teams),
            'final_team_id' => $plan['team_id'],
            'final_team_code' => $plan['code'],
            'final_team_name' => $team?->name ?? $plan['name'],
            'action' => $plan['action'],
            'project_id' => $team?->project_id,
            'supervisor_id' => $team?->supervisor_id,
            'reports_to_changed' => false,
            'role_action' => 'ENSURE_TEAM_ROLE:koordinator_sales',
        ];
    }

    /** @param array<string, list<array<string, int>>> $accounting
     * @param  list<int>  $mappedSales
     * @param  list<array<string, mixed>>  $finalPlans
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, User>  $users
     * @return list<array<string, mixed>>
     */
    private function salesReport(array $accounting, array $mappedSales, array $finalPlans, EloquentCollection $teams, EloquentCollection $users): array
    {
        $coordinatorBySales = [];
        foreach ($finalPlans as $plan) {
            foreach ($plan['sales_ids'] as $salesId) {
                $coordinatorBySales[$salesId] = $plan['coordinator_id'];
            }
        }

        $rows = [];
        foreach (self::SALES_CATEGORIES as $category) {
            foreach ($accounting[$category] as $entry) {
                $userId = $entry['next_user_id'];
                $user = $users->firstWhere('id', $userId);
                $plan = $coordinatorBySales[$userId] ?? null;
                $team = $plan !== null ? collect($finalPlans)->firstWhere('coordinator_id', $plan) : null;
                $rows[] = [
                    'user_id' => $userId,
                    'category' => $category,
                    'legacy_user_id' => $entry['legacy_user_id'],
                    'branch_id' => $user?->branch_id,
                    'previous_team_ids' => $this->legacyTeamIdsForUsers([$userId], $teams),
                    'coordinator_id' => $plan,
                    'final_team_id' => $team['team_id'] ?? null,
                    'final_team_code' => $team['code'] ?? null,
                    'membership_action' => $category === 'MAPPED_ACTIVE' ? 'MOVE_FROM_LEGACY_TO_FINAL' : 'PRESERVE_UNTOUCHED',
                    'role_action' => $category === 'MAPPED_ACTIVE' ? 'ENSURE_TEAM_ROLE:sales' : 'PRESERVE_UNTOUCHED',
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, list<array<string, int>>> $accounting
     * @return array<string, list<array<string, int|null>>>
     */
    private function accountingReport(array $accounting): array
    {
        return collect($accounting)->map(fn (array $entries): array => array_values($entries))->all();
    }

    /** @param EloquentCollection<int, Team> $legacyTeams
     * @param  EloquentCollection<int, Team>  $teams
     * @param  list<int>  $mappedUsers
     * @return list<array<string, mixed>>
     */
    private function legacyTeamReport(EloquentCollection $legacyTeams, EloquentCollection $teams, array $mappedUsers): array
    {
        return $legacyTeams->map(function (Team $team) use ($mappedUsers): array {
            $memberIds = $team->users->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $projected = array_values(array_diff($memberIds, $mappedUsers));

            return [
                'team_id' => $team->id,
                'code' => $team->code,
                'name' => $team->name,
                'is_active_before' => (bool) $team->is_active,
                'member_ids_before' => $memberIds,
                'member_ids_after_confirmed_move' => $projected,
                'will_be_empty_after_confirmed_move' => $projected === [],
                'coordinator_id_before' => $team->coordinator_id,
                'coordinator_id_after_confirmed_move' => null,
                'will_be_deactivated' => false,
            ];
        })->values()->all();
    }

    /** @param list<array<string, mixed>> $finalPlans
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, User>  $users
     * @return array<string, mixed>
     */
    private function leadReport(array $finalPlans, EloquentCollection $teams, EloquentCollection $users): array
    {
        if (! Schema::hasTable('leads')) {
            return ['total' => 0, 'existing_consistent' => 0, 'safe_auto_map_candidate' => 0, 'mismatch_needs_review' => 0, 'modified' => false];
        }

        $plansBySales = $this->plansBySales($finalPlans);
        $legacyTeamIds = $teams->filter(fn (Team $team): bool => $this->isLegacyTeam($team))->pluck('id')->all();
        $counts = ['total' => 0, 'existing_consistent' => 0, 'safe_auto_map_candidate' => 0, 'mismatch_needs_review' => 0, 'approved_historical_exception' => 0, 'modified' => false, 'examples' => []];
        $approvedLeadIds = array_map(
            fn (int $sourceLeadId): string => 'CRM-'.$sourceLeadId,
            config('legacy-migration.approved_historical_cross_branch_source_lead_ids', []),
        );
        $excludedLeadIds = Schema::hasTable('lead_cutover_dispositions')
            ? DB::table('lead_cutover_dispositions')->whereIn('classification', ['EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED'])->pluck('lead_id')->map(fn ($id): int => (int) $id)->all()
            : [];

        foreach (DB::table('leads')->select(['id', 'id_lead', 'branch_id', 'team_id', 'sales_pic_id'])->orderBy('id')->get() as $lead) {
            if (in_array((string) $lead->id_lead, $approvedLeadIds, true) || in_array((int) $lead->id, $excludedLeadIds, true)) {
                $counts['approved_historical_exception']++;

                continue;
            }

            $counts['total']++;
            $sales = $lead->sales_pic_id !== null ? $users->get((int) $lead->sales_pic_id) : null;
            $targetPlan = $plansBySales[(int) ($lead->sales_pic_id ?? 0)] ?? null;
            $targetTeamId = $targetPlan['team_id'] ?? null;
            $currentTeam = $lead->team_id !== null ? $teams->firstWhere('id', $lead->team_id) : null;
            if ($sales instanceof User && $targetPlan !== null && $targetTeamId !== null && (int) $sales->branch_id === (int) $lead->branch_id && (int) $lead->team_id === (int) $targetTeamId) {
                $counts['existing_consistent']++;
            } elseif ($sales instanceof User && $targetPlan !== null && (int) $sales->branch_id === (int) $lead->branch_id && ($lead->team_id === null || in_array((int) $lead->team_id, $legacyTeamIds, true))) {
                $counts['safe_auto_map_candidate']++;
            } else {
                $counts['mismatch_needs_review']++;
            }

            if (count($counts['examples']) < 20 && ($targetTeamId === null || (int) $lead->team_id !== (int) $targetTeamId)) {
                $counts['examples'][] = ['lead_id' => $lead->id, 'team_id' => $lead->team_id, 'sales_pic_id' => $lead->sales_pic_id, 'target_team_id' => $targetTeamId, 'current_team_is_legacy' => $currentTeam instanceof Team && $this->isLegacyTeam($currentTeam)];
            }
        }

        return $counts;
    }

    /** @param list<array<string, mixed>> $finalPlans
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, User>  $users
     * @param  Collection<int, list<string>>  $rolesByUser
     * @return array<string, mixed>
     */
    private function agendaReport(array $finalPlans, EloquentCollection $teams, EloquentCollection $users, Collection $rolesByUser): array
    {
        if (! Schema::hasTable('sales_agendas')) {
            return [
                'total' => 0,
                'existing_consistent' => 0,
                'safe_auto_map_candidate' => 0,
                'valid_sales_owned' => 0,
                'valid_coordinator_owned' => 0,
                'coordinator_owned_resolved' => 0,
                'mismatch_needs_review' => 0,
                'approved_historical_exception' => 0,
                'modified' => false,
                'planned_changes' => [],
            ];
        }

        $plansBySales = $this->plansBySales($finalPlans);
        $plansByCoordinator = $this->plansByCoordinator($finalPlans);
        $legacyTeamIds = $teams->filter(fn (Team $team): bool => $this->isLegacyTeam($team))->pluck('id')->all();
        $counts = [
            'total' => 0,
            'existing_consistent' => 0,
            'safe_auto_map_candidate' => 0,
            'valid_sales_owned' => 0,
            'valid_coordinator_owned' => 0,
            'coordinator_owned_resolved' => 0,
            'mismatch_needs_review' => 0,
            'approved_historical_exception' => 0,
            'modified' => false,
        ];
        $plannedChanges = [];
        foreach (DB::table('sales_agendas')->select(['id', 'branch_id', 'team_id', 'owner_id', 'agenda_date'])->orderBy('id')->get() as $agenda) {
            $counts['total']++;
            $owner = $agenda->owner_id !== null ? $users->get((int) $agenda->owner_id) : null;
            $ownerRole = $this->agendaOwnerRole($rolesByUser->get((int) ($agenda->owner_id ?? 0), []));
            $targetPlan = match ($ownerRole) {
                'sales' => $plansBySales[(int) $agenda->owner_id] ?? null,
                'koordinator_sales' => $plansByCoordinator[(int) $agenda->owner_id] ?? null,
                default => null,
            };
            $targetTeamId = $targetPlan['team_id'] ?? null;
            $validOwner = $owner instanceof User
                && $targetPlan !== null
                && (int) $owner->branch_id === (int) $agenda->branch_id;
            if ($validOwner && $ownerRole === 'sales') {
                $counts['valid_sales_owned']++;
            }
            if ($validOwner && $ownerRole === 'koordinator_sales') {
                $counts['valid_coordinator_owned']++;
                $counts['coordinator_owned_resolved']++;
            }

            if ($validOwner && $targetTeamId !== null && (int) $agenda->team_id === (int) $targetTeamId) {
                $counts['existing_consistent']++;
            } elseif ($validOwner && ($agenda->team_id === null || in_array((int) $agenda->team_id, $legacyTeamIds, true))) {
                if ($ownerRole === 'sales') {
                    $counts['safe_auto_map_candidate']++;
                }
                $plannedChanges[] = [
                    'agenda_id' => (int) $agenda->id,
                    'owner_id' => (int) $agenda->owner_id,
                    'owner_role' => $ownerRole === 'koordinator_sales' ? 'coordinator' : 'sales',
                    'coordinator_id' => (int) $targetPlan['coordinator_id'],
                    'current_team_id' => $agenda->team_id === null ? null : (int) $agenda->team_id,
                    'target_team_id' => $targetTeamId,
                    'target_team_code' => $targetPlan['code'],
                ];
            } elseif ($agenda->agenda_date !== null && CarbonImmutable::parse((string) $agenda->agenda_date)->lessThan(now()->startOfMonth())) {
                $counts['approved_historical_exception']++;
            } else {
                $counts['mismatch_needs_review']++;
            }
        }

        return [...$counts, 'planned_changes' => $plannedChanges];
    }

    /** @return array<string, mixed> */
    private function salesCaseReport(): array
    {
        if (! Schema::hasTable('sales_cases')) {
            return ['total' => 0, 'active' => 0, 'modified' => false, 'note' => 'Sales Cases table is unavailable.'];
        }

        $active = DB::table('sales_cases')->whereNull('cancelled_at')->count();

        return ['total' => (int) DB::table('sales_cases')->count(), 'active' => (int) $active, 'modified' => false, 'note' => 'Sales Cases are audited only; no Team or Sales PIC ownership is rewritten.'];
    }

    /** @param array<string, mixed> $report */
    private function readinessBlockers(array $report): array
    {
        $blockers = [];
        if (($report['counts']['sales_unassigned_active'] ?? 0) > 0) {
            $blockers[] = ['code' => 'unassigned_active_sales', 'message' => 'An active Sales user has no authoritative Coordinator mapping.', 'count' => $report['counts']['sales_unassigned_active']];
        }
        if (($report['leads']['mismatch_needs_review'] ?? 0) > 0) {
            $blockers[] = ['code' => 'lead_mismatches_needs_review', 'message' => 'Lead ownership mismatches remain outside this normalization command.', 'count' => $report['leads']['mismatch_needs_review']];
        }
        if (($report['agendas']['mismatch_needs_review'] ?? 0) > 0) {
            $blockers[] = ['code' => 'agenda_mismatches_needs_review', 'message' => 'Agenda ownership mismatches require separate reconciliation.', 'count' => $report['agendas']['mismatch_needs_review']];
        }

        return $blockers;
    }

    /** @param array<string, mixed> $report
     * @param  EloquentCollection<int, Team>  $legacyTeams
     * @param  EloquentCollection<int, Team>  $teams
     * @param  list<int>  $mappedUsers
     * @return list<array<string, mixed>>
     */
    private function deactivationBlockers(array $report, EloquentCollection $legacyTeams, EloquentCollection $teams, array $mappedUsers): array
    {
        $blockers = [];
        foreach ($report['readiness_blockers'] as $blocker) {
            $blockers[] = ['code' => 'legacy_deactivation_'.$blocker['code'], 'message' => 'Legacy Teams cannot be deactivated while readiness remains blocked: '.$blocker['message'], 'details' => ['count' => $blocker['count'] ?? null]];
        }

        foreach ($legacyTeams as $team) {
            $remaining = $team->users->pluck('id')->filter(fn ($id): bool => ! in_array((int) $id, $mappedUsers, true));
            if ($remaining->isNotEmpty()) {
                $blockers[] = ['code' => 'legacy_team_still_has_members', 'message' => 'A legacy Team still has users that were not safely moved.', 'details' => ['team_id' => $team->id, 'user_ids' => $remaining->values()->all()]];
            }
        }

        return $blockers;
    }

    /** @param array<string, mixed> $context
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function applyPlan(array $context, array $report, bool $deactivateLegacy): array
    {
        $report['transaction']['applied'] = true;
        $report['transaction']['old_team_memberships'] = $this->oldMembershipRows($context['legacy_team_ids'], $context['mapped_users']);
        $report['transaction']['old_team_roles'] = $this->oldRoleRows($context['legacy_team_ids'], $context['mapped_users']);
        $report['transaction']['old_team_states'] = DB::table('teams')->whereIn('id', $context['legacy_team_ids'])->get(['id', 'is_active', 'coordinator_id', 'supervisor_id', 'project_id'])->map(fn (object $row): array => (array) $row)->all();

        Rbac::provision();
        $finalTeamIds = [];
        $finalTeamIdsByCoordinator = [];
        foreach ($context['final_plans'] as $index => $plan) {
            $team = $plan['team_id'] !== null ? Team::query()->findOrFail($plan['team_id']) : Team::query()->create([
                'parent_id' => null,
                'branch_id' => $plan['branch_id'],
                'project_id' => null,
                'supervisor_id' => null,
                'coordinator_id' => $plan['coordinator_id'],
                'code' => $plan['code'],
                'name' => $plan['name'],
                'is_active' => true,
            ]);
            $finalTeamIds[] = (int) $team->id;
            $finalTeamIdsByCoordinator[(int) $plan['coordinator_id']] = (int) $team->id;
            Rbac::provisionTeam($team);
            $this->ensureMembershipAndRole($team, (int) $plan['coordinator_id'], 'koordinator_sales');
            foreach ($plan['sales_ids'] as $salesId) {
                $this->ensureMembershipAndRole($team, (int) $salesId, 'sales');
            }
            $report['coordinators'][$index]['final_team_id'] = (int) $team->id;
            $report['coordinators'][$index]['final_team_code'] = $team->code;
            $report['coordinators'][$index]['final_team_name'] = $team->name;
        }

        foreach ($report['sales'] as &$salesReport) {
            if ($salesReport['coordinator_id'] === null || $salesReport['category'] !== 'MAPPED_ACTIVE') {
                continue;
            }

            $salesReport['final_team_id'] = $finalTeamIdsByCoordinator[$salesReport['coordinator_id']] ?? null;
            $salesReport['membership_action'] = 'MOVED_FROM_LEGACY_TO_FINAL';
        }
        unset($salesReport);

        $agendaTeamUpdates = $this->applyAgendaTeamAssignments($report['agendas']['planned_changes'] ?? [], $finalTeamIdsByCoordinator);
        $report['counts']['agenda_team_updates'] = $agendaTeamUpdates['count'];
        $report['agendas']['modified'] = $agendaTeamUpdates['count'] > 0;
        $report['transaction']['agenda_team_updates'] = $agendaTeamUpdates['agenda_ids'];

        if ($context['legacy_team_ids'] !== [] && $context['mapped_users'] !== []) {
            DB::table('model_has_roles')->whereIn('team_id', $context['legacy_team_ids'])->where('model_type', User::class)->whereIn('model_id', $context['mapped_users'])->delete();
            DB::table('team_user')->whereIn('team_id', $context['legacy_team_ids'])->whereIn('user_id', $context['mapped_users'])->delete();
        }

        $mappedCoordinatorIds = collect($context['final_plans'])->pluck('coordinator_id')->map(fn ($id): int => (int) $id)->all();
        if ($context['legacy_team_ids'] !== [] && $mappedCoordinatorIds !== []) {
            DB::table('teams')->whereIn('id', $context['legacy_team_ids'])->whereIn('coordinator_id', $mappedCoordinatorIds)->update(['coordinator_id' => null, 'updated_at' => now()]);
        }

        if ($deactivateLegacy) {
            DB::table('teams')->whereIn('id', $context['legacy_team_ids'])->update(['is_active' => false, 'updated_at' => now()]);
            $report['counts']['legacy_teams_deactivated'] = count($context['legacy_team_ids']);
            foreach ($report['legacy_teams'] as &$legacyTeam) {
                $legacyTeam['will_be_deactivated'] = true;
            }
            unset($legacyTeam);
        }

        $report['transaction']['created_team_ids'] = array_values(array_diff($finalTeamIds, array_filter(array_map(fn (array $plan): ?int => $plan['team_id'], $context['final_plans']))));
        $report['final_team_ids'] = $finalTeamIds;

        return $report;
    }

    private function ensureMembershipAndRole(Team $team, int $userId, string $roleName): void
    {
        $membershipQuery = DB::table('team_user')->where('team_id', $team->id)->where('user_id', $userId);
        if ($membershipQuery->exists()) {
            $membershipQuery->update(['is_default' => true, 'updated_at' => now()]);
        } else {
            DB::table('team_user')->insert([
                'team_id' => $team->id,
                'user_id' => $userId,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleId = Role::query()->where('team_id', $team->id)->where('name', $roleName)->value('id');
        if ($roleId === null) {
            throw new \RuntimeException(sprintf('Team role %s is not available for Team %d.', $roleName, $team->id));
        }

        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $roleId,
            'model_id' => $userId,
            'model_type' => User::class,
            'team_id' => $team->id,
        ]);
    }

    /** @param list<int> $teamIds
     * @param  list<int>  $userIds
     * @return list<array<string, mixed>>
     */
    private function oldMembershipRows(array $teamIds, array $userIds): array
    {
        return DB::table('team_user')->whereIn('team_id', $teamIds)->whereIn('user_id', $userIds)->get()->map(fn (object $row): array => (array) $row)->all();
    }

    /** @param list<int> $teamIds
     * @param  list<int>  $userIds
     * @return list<array<string, mixed>>
     */
    private function oldRoleRows(array $teamIds, array $userIds): array
    {
        return DB::table('model_has_roles')->whereIn('team_id', $teamIds)->where('model_type', User::class)->whereIn('model_id', $userIds)->get()->map(fn (object $row): array => (array) $row)->all();
    }

    /** @param list<int> $userIds
     * @param  EloquentCollection<int, Team>  $teams
     * @return list<int>
     */
    private function legacyTeamIdsForUsers(array $userIds, EloquentCollection $teams): array
    {
        return $teams->filter(fn (Team $team): bool => $this->isLegacyTeam($team) && $team->users->pluck('id')->intersect($userIds)->isNotEmpty())->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /** @param list<array<string, mixed>> $finalPlans
     * @return array<int, array<string, mixed>>
     */
    private function plansBySales(array $finalPlans): array
    {
        $result = [];
        foreach ($finalPlans as $plan) {
            foreach ($plan['sales_ids'] as $salesId) {
                $result[(int) $salesId] = $plan;
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $finalPlans
     * @return array<int, array<string, mixed>>
     */
    private function plansByCoordinator(array $finalPlans): array
    {
        $result = [];
        foreach ($finalPlans as $plan) {
            $result[(int) $plan['coordinator_id']] = $plan;
        }

        return $result;
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function agendaOwnerRole(array $roleNames): ?string
    {
        $supportedRoles = array_values(array_intersect(['sales', 'koordinator_sales'], $roleNames));

        return count($supportedRoles) === 1 ? $supportedRoles[0] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $plannedChanges
     * @param  array<int, int>  $finalTeamIdsByCoordinator
     * @return array{count: int, agenda_ids: list<int>}
     */
    private function applyAgendaTeamAssignments(array $plannedChanges, array $finalTeamIdsByCoordinator): array
    {
        $agendaIds = [];
        foreach ($plannedChanges as $change) {
            $targetTeamId = $finalTeamIdsByCoordinator[(int) $change['coordinator_id']] ?? null;
            if ($targetTeamId === null) {
                throw new \RuntimeException('Agenda owner does not resolve to an applied final Team.');
            }

            if ((int) ($change['current_team_id'] ?? 0) === $targetTeamId) {
                continue;
            }

            DB::table('sales_agendas')->where('id', $change['agenda_id'])->update([
                'team_id' => $targetTeamId,
                'updated_at' => now(),
            ]);
            $agendaIds[] = (int) $change['agenda_id'];
        }

        return ['count' => count($agendaIds), 'agenda_ids' => $agendaIds];
    }

    private function teamCode(int $branchId, int $coordinatorId): string
    {
        return sprintf('COORD-B%02d-U%d', $branchId, $coordinatorId);
    }

    private function isLegacyTeam(Team $team): bool
    {
        return str_starts_with((string) $team->name, 'Migrasi Tim')
            || str_starts_with((string) $team->name, 'Legacy ')
            || str_starts_with((string) $team->code, 'SRC-TEAM-BRANCH-')
            || str_starts_with((string) $team->code, 'LEGACY-');
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\]/', $path) === 1
            ? $path
            : base_path($path);
    }

    /** @param array<string, mixed> $report
     * @param  array<string, mixed>  $details
     */
    private function addPreflightError(array &$report, string $code, string $message, array $details = []): void
    {
        $report['preflight_errors'][] = ['code' => $code, 'message' => $message, 'details' => $details];
    }
}
