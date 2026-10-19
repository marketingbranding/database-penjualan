<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class LeadTeamReassignmentService
{
    private const FORMAT_VERSION = 1;

    /** @var list<string> */
    private const SALES_ACCOUNTING_CATEGORIES = [
        'MAPPED_ACTIVE',
        'UNASSIGNED_ACTIVE',
        'INACTIVE',
        'ANONYMIZED',
        'LEGACY_ONLY',
        'DUPLICATE_DATA_ISSUE',
        'OTHER',
    ];

    /**
     * Audit and, optionally, reassign only deterministic Lead team context.
     *
     * @return array<string, mixed>
     */
    public function run(string $mappingPath, bool $apply = false): array
    {
        $report = $this->emptyReport($mappingPath, $apply);

        try {
            $mapping = $this->readMapping($mappingPath, $report);
            $context = $this->buildContext($mapping, $report);
            $report = $context['report'];

            if ($report['preflight_errors'] !== []) {
                $report['status'] = 'BLOCKED';

                return $report;
            }

            if (! $apply) {
                $report['status'] = 'DRY_RUN';

                return $report;
            }

            $report = DB::transaction(function () use ($context, $report): array {
                return $this->apply($context, $report);
            });
            $report['status'] = 'APPLIED';

            return $report;
        } catch (Throwable $exception) {
            $report['status'] = 'FAILED';
            $report['transaction']['rolled_back'] = $apply;
            $report['errors'][] = [
                'code' => 'lead_reassignment_failed',
                'message' => $exception->getMessage(),
            ];

            report($exception);

            return $report;
        }
    }

    /** @return array<string, mixed> */
    private function emptyReport(string $mappingPath, bool $apply): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'mapping_path' => $mappingPath,
            'mapping_sha256' => null,
            'status' => 'PREFLIGHT',
            'preflight_errors' => [],
            'errors' => [],
            'counts' => [
                'leads_inspected' => 0,
                'existing_consistent' => 0,
                'safe_reassign' => 0,
                'needs_review' => 0,
                'excluded' => 0,
                'changed' => 0,
                'unassigned_active_sales_leads' => 0,
                'inactive_sales_leads' => 0,
            ],
            'reason_breakdown' => [],
            'sales_exceptions' => [
                'unassigned_active' => [],
                'inactive' => [],
            ],
            'classifications' => [],
            'planned_changes' => [],
            'applied_changes' => [],
            'field_changes' => [
                'team_id' => 0,
                'sales_pic_id' => 0,
                'created_by' => 0,
                'status_lead' => 0,
                'lead_activity_rows' => 0,
                'timestamps' => 0,
            ],
            'transaction' => [
                'applied' => false,
                'rolled_back' => false,
                'rollback_evidence' => [],
            ],
        ];
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function readMapping(string $mappingPath, array &$report): array
    {
        $absolutePath = $this->absolutePath($mappingPath);
        if (! File::exists($absolutePath)) {
            $this->addPreflightError($report, 'mapping_missing', 'The authoritative organization mapping file does not exist.', ['path' => $absolutePath]);

            return [];
        }

        $report['mapping_sha256'] = hash_file('sha256', $absolutePath) ?: null;
        $mapping = json_decode((string) File::get($absolutePath), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($mapping)) {
            $this->addPreflightError($report, 'mapping_invalid', 'The organization mapping must contain a JSON object.');

            return [];
        }
        if ((int) ($mapping['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            $this->addPreflightError($report, 'mapping_format_invalid', 'The organization mapping format is unsupported.', ['expected' => self::FORMAT_VERSION]);
        }

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $report
     * @return array{report: array<string, mixed>, classifications: list<array<string, mixed>>, final_teams: EloquentCollection<int, Team>}
     */
    private function buildContext(array $mapping, array $report): array
    {
        $categoryByUser = $this->salesAccountingCategories($mapping, $report);
        $finalTeams = Team::query()
            ->where('is_active', true)
            ->whereNotNull('coordinator_id')
            ->where('code', 'like', 'COORD-B%')
            ->orderBy('id')
            ->get(['id', 'branch_id', 'coordinator_id', 'code', 'name', 'is_active']);

        if ($finalTeams->isEmpty()) {
            $this->addPreflightError($report, 'final_teams_missing', 'No active normalized Coordinator Teams were found.');
        }

        $teamGraph = $this->teamGraph($finalTeams, $report);
        $classifications = [];

        /** @var Lead $lead */
        foreach (Lead::query()
            ->select(['id', 'branch_id', 'team_id', 'sales_pic_id'])
            ->with([
                'salesPic:id,branch_id',
                'team:id,branch_id,code,name,is_active',
            ])
            ->orderBy('id')
            ->get() as $lead) {
            $classifications[] = $this->classifyLead($lead, $categoryByUser, $teamGraph, $finalTeams);
        }

        $report = $this->summarize($report, $classifications, $categoryByUser);

        return [
            'report' => $report,
            'classifications' => $classifications,
            'final_teams' => $finalTeams,
        ];
    }

    /** @param array<string, mixed> $mapping
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function salesAccountingCategories(array $mapping, array &$report): array
    {
        if (! is_array($mapping['sales_accounting'] ?? null)) {
            $this->addPreflightError($report, 'sales_accounting_missing', 'The mapping must classify Sales-role users before Lead reassignment.');

            return [];
        }

        $categoryByUser = [];
        $accountedIds = [];
        foreach (self::SALES_ACCOUNTING_CATEGORIES as $category) {
            $entries = $mapping['sales_accounting'][$category] ?? [];
            if (! is_array($entries)) {
                $this->addPreflightError($report, 'sales_accounting_category_invalid', 'Sales accounting category must be an array.', ['category' => $category]);

                continue;
            }

            foreach ($entries as $entry) {
                $userId = is_array($entry) ? (int) ($entry['next_user_id'] ?? 0) : (int) $entry;
                if ($userId < 1) {
                    $this->addPreflightError($report, 'sales_accounting_user_invalid', 'Sales accounting contains an invalid user ID.', ['category' => $category]);

                    continue;
                }
                if (isset($categoryByUser[$userId])) {
                    $this->addPreflightError($report, 'sales_accounting_duplicate', 'A Sales user appears in multiple accounting categories.', ['user_id' => $userId]);

                    continue;
                }

                $categoryByUser[$userId] = $category;
                $accountedIds[] = $userId;
            }
        }

        if ($accountedIds !== []) {
            $existingIds = User::query()->whereIn('id', $accountedIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $missingIds = array_values(array_diff($accountedIds, $existingIds));
            if ($missingIds !== []) {
                $this->addPreflightError($report, 'sales_accounting_user_missing', 'Sales accounting references users that do not exist in OASIS Next.', ['user_ids' => $missingIds]);
            }
        }

        return $categoryByUser;
    }

    /**
     * @param  EloquentCollection<int, Team>  $finalTeams
     * @param  array<string, mixed>  $report
     * @return array{role_team_ids: array<int, list<int>>, membership_team_ids: array<int, list<int>>, valid_sales_team_ids: array<int, list<int>>}
     */
    private function teamGraph(EloquentCollection $finalTeams, array &$report): array
    {
        $teamIds = $finalTeams->modelKeys();
        if ($teamIds === []) {
            return ['role_team_ids' => [], 'membership_team_ids' => [], 'valid_sales_team_ids' => []];
        }

        $modelType = (new User)->getMorphClass();
        $roleRows = DB::table('model_has_roles as model_roles')
            ->join('roles', 'roles.id', '=', 'model_roles.role_id')
            ->where('model_roles.model_type', $modelType)
            ->where('roles.name', 'sales')
            ->whereIn('roles.team_id', $teamIds)
            ->get(['model_roles.model_id as user_id', 'roles.team_id']);
        $membershipRows = DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->get(['user_id', 'team_id']);
        $coordinatorRoles = DB::table('model_has_roles as model_roles')
            ->join('roles', 'roles.id', '=', 'model_roles.role_id')
            ->where('model_roles.model_type', $modelType)
            ->where('roles.name', 'koordinator_sales')
            ->whereIn('roles.team_id', $teamIds)
            ->get(['model_roles.model_id as user_id', 'roles.team_id']);
        $coordinators = User::query()->whereIn('id', $finalTeams->pluck('coordinator_id')->all())->get(['id', 'branch_id'])->keyBy('id');

        foreach ($finalTeams as $team) {
            $coordinator = $coordinators->get($team->coordinator_id);
            if (! $coordinator instanceof User) {
                $this->addPreflightError($report, 'final_team_coordinator_missing', 'A normalized final Team points to a missing Coordinator.', ['team_id' => $team->id, 'coordinator_id' => $team->coordinator_id]);
            } elseif ($team->branch_id === null || (int) $coordinator->branch_id !== (int) $team->branch_id) {
                $this->addPreflightError($report, 'final_team_coordinator_branch_mismatch', 'A normalized final Team Coordinator does not belong to the Team Branch.', ['team_id' => $team->id, 'coordinator_id' => $team->coordinator_id, 'branch_id' => $team->branch_id]);
            }
            $hasCoordinatorMembership = $membershipRows->contains(fn (object $row): bool => (int) $row->team_id === (int) $team->id && (int) $row->user_id === (int) $team->coordinator_id);
            $hasCoordinatorRole = $coordinatorRoles->contains(fn (object $row): bool => (int) $row->team_id === (int) $team->id && (int) $row->user_id === (int) $team->coordinator_id);
            if (! $hasCoordinatorMembership || ! $hasCoordinatorRole) {
                $this->addPreflightError($report, 'final_team_coordinator_incomplete', 'A normalized final Team is missing its Coordinator membership or role.', ['team_id' => $team->id, 'coordinator_id' => $team->coordinator_id]);
            }
        }

        $roleTeamIds = $roleRows->groupBy('user_id')->map(fn ($rows): array => $rows->pluck('team_id')->map(fn ($id): int => (int) $id)->unique()->values()->all())->all();
        $membershipTeamIds = $membershipRows->groupBy('user_id')->map(fn ($rows): array => $rows->pluck('team_id')->map(fn ($id): int => (int) $id)->unique()->values()->all())->all();
        $validSalesTeamIds = [];
        foreach (array_unique(array_merge(array_keys($roleTeamIds), array_keys($membershipTeamIds))) as $userId) {
            $validSalesTeamIds[(int) $userId] = array_values(array_intersect($roleTeamIds[$userId] ?? [], $membershipTeamIds[$userId] ?? []));
        }

        return [
            'role_team_ids' => $roleTeamIds,
            'membership_team_ids' => $membershipTeamIds,
            'valid_sales_team_ids' => $validSalesTeamIds,
        ];
    }

    /**
     * @param  array<int, string>  $categoryByUser
     * @param  array{role_team_ids: array<int, list<int>>, membership_team_ids: array<int, list<int>>, valid_sales_team_ids: array<int, list<int>>}  $teamGraph
     * @param  EloquentCollection<int, Team>  $finalTeams
     * @return array<string, mixed>
     */
    private function classifyLead(Lead $lead, array $categoryByUser, array $teamGraph, EloquentCollection $finalTeams): array
    {
        $base = [
            'lead_id' => (int) $lead->id,
            'branch_id' => $lead->branch_id !== null ? (int) $lead->branch_id : null,
            'old_team_id' => $lead->team_id !== null ? (int) $lead->team_id : null,
            'sales_pic_id' => $lead->sales_pic_id !== null ? (int) $lead->sales_pic_id : null,
            'new_team_id' => null,
            'category' => null,
            'reason' => null,
            'rule' => 'Lead Team follows sales_pic_id final operational Team',
        ];

        if ($lead->sales_pic_id === null) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'missing_sales_pic'];
        }

        if (! $lead->salesPic instanceof User) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'sales_user_missing'];
        }

        $salesPicId = (int) $lead->sales_pic_id;
        $accountingCategory = $categoryByUser[$salesPicId] ?? null;
        $validTeamIds = $teamGraph['valid_sales_team_ids'][$salesPicId] ?? [];
        if ($accountingCategory !== 'MAPPED_ACTIVE') {
            if ($accountingCategory === null && $validTeamIds === []) {
                return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'sales_pic_has_no_final_team'];
            }

            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => $this->salesExceptionReason($accountingCategory)];
        }

        if (count($validTeamIds) > 1
            || count($teamGraph['role_team_ids'][$salesPicId] ?? []) > 1
            || count($teamGraph['membership_team_ids'][$salesPicId] ?? []) > 1) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'sales_has_multiple_final_teams'];
        }
        if ($validTeamIds === []) {
            $reason = ($teamGraph['role_team_ids'][$salesPicId] ?? []) !== [] && ($teamGraph['membership_team_ids'][$salesPicId] ?? []) !== []
                ? 'sales_final_team_role_membership_conflict'
                : 'sales_pic_has_no_final_team';

            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => $reason];
        }

        $targetTeam = $finalTeams->firstWhere('id', $validTeamIds[0]);
        if (! $targetTeam instanceof Team) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'target_team_missing'];
        }
        if ((int) $lead->salesPic->branch_id !== (int) $targetTeam->branch_id) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'sales_team_branch_mismatch'];
        }
        if ($lead->branch_id === null || (int) $lead->branch_id !== (int) $targetTeam->branch_id) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'lead_branch_team_mismatch'];
        }

        if ($lead->team_id !== null && ! $lead->team instanceof Team) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'current_team_missing'];
        }

        if ($lead->team_id !== null && (int) $lead->team_id === (int) $targetTeam->id) {
            return [...$base, 'category' => 'EXISTING_CONSISTENT', 'new_team_id' => (int) $targetTeam->id];
        }

        if ($lead->team instanceof Team && (int) $lead->team->branch_id !== (int) $lead->branch_id) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'current_team_branch_mismatch'];
        }

        if ($lead->team instanceof Team && ! $this->isLegacyTeam($lead->team)) {
            return [...$base, 'category' => 'NEEDS_REVIEW', 'reason' => 'current_team_conflict'];
        }

        return [...$base, 'category' => 'SAFE_REASSIGN', 'new_team_id' => (int) $targetTeam->id, 'reason' => 'legacy_or_empty_team_to_sales_final_team'];
    }

    /** @param array<string, mixed> $report
     * @param  list<array<string, mixed>>  $classifications
     * @param  array<int, string>  $categoryByUser
     * @return array<string, mixed>
     */
    private function summarize(array $report, array $classifications, array $categoryByUser): array
    {
        $reasonCounts = [];
        $salesExceptionLeads = [
            'unassigned_active' => [],
            'inactive' => [],
        ];
        $plannedChanges = [];

        foreach ($classifications as $classification) {
            $report['counts']['leads_inspected']++;
            $category = $classification['category'];
            if ($category === 'EXISTING_CONSISTENT') {
                $report['counts']['existing_consistent']++;
            } elseif ($category === 'SAFE_REASSIGN') {
                $report['counts']['safe_reassign']++;
                $plannedChanges[] = $this->changeEvidence($classification);
            } elseif ($category === 'EXCLUDED') {
                $report['counts']['excluded']++;
            } else {
                $report['counts']['needs_review']++;
                $reason = $classification['reason'] ?? 'unknown';
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
                if ($reason === 'unassigned_active_sales') {
                    $salesExceptionLeads['unassigned_active'][] = $classification['lead_id'];
                }
                if ($reason === 'inactive_sales') {
                    $salesExceptionLeads['inactive'][] = $classification['lead_id'];
                }
            }
        }

        $report['reason_breakdown'] = $reasonCounts;
        $report['sales_exceptions']['unassigned_active'] = $this->salesExceptionReport('UNASSIGNED_ACTIVE', $categoryByUser, $salesExceptionLeads['unassigned_active']);
        $report['sales_exceptions']['inactive'] = $this->salesExceptionReport('INACTIVE', $categoryByUser, $salesExceptionLeads['inactive']);
        $report['counts']['unassigned_active_sales_leads'] = count($salesExceptionLeads['unassigned_active']);
        $report['counts']['inactive_sales_leads'] = count($salesExceptionLeads['inactive']);
        $report['classifications'] = $classifications;
        $report['planned_changes'] = $plannedChanges;
        $report['field_changes']['team_id'] = count($plannedChanges);

        return $report;
    }

    /** @param array<string, mixed> $classification
     * @return array<string, mixed>
     */
    private function changeEvidence(array $classification): array
    {
        return [
            'lead_id' => $classification['lead_id'],
            'old_team_id' => $classification['old_team_id'],
            'new_team_id' => $classification['new_team_id'],
            'sales_pic_id' => $classification['sales_pic_id'],
            'branch_id' => $classification['branch_id'],
            'reason' => $classification['reason'],
            'rule' => $classification['rule'],
        ];
    }

    /** @param array<int, string> $categoryByUser
     * @param  list<int>  $leadIds
     * @return array<string, mixed>
     */
    private function salesExceptionReport(string $category, array $categoryByUser, array $leadIds): array
    {
        return [
            'category' => $category,
            'user_ids' => array_keys(array_filter($categoryByUser, fn (string $value): bool => $value === $category)),
            'lead_count' => count($leadIds),
            'lead_ids' => $leadIds,
        ];
    }

    /** @param array<string, mixed> $context
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function apply(array $context, array $report): array
    {
        $report['transaction']['applied'] = true;
        $appliedChanges = [];

        foreach ($context['classifications'] as $classification) {
            if ($classification['category'] !== 'SAFE_REASSIGN') {
                continue;
            }

            $query = DB::table('leads')->where('id', $classification['lead_id'])->lockForUpdate();
            if ($classification['old_team_id'] === null) {
                $query->whereNull('team_id');
            } else {
                $query->where('team_id', $classification['old_team_id']);
            }

            $changed = $query->update(['team_id' => $classification['new_team_id']]);
            if ($changed !== 1) {
                throw new RuntimeException(sprintf('Lead #%d changed after preflight; reassignment was rolled back.', $classification['lead_id']));
            }

            $appliedChanges[] = $this->changeEvidence($classification);
        }

        $report['applied_changes'] = $appliedChanges;
        $report['counts']['changed'] = count($appliedChanges);
        $report['transaction']['rollback_evidence'] = $appliedChanges;

        return $report;
    }

    private function salesExceptionReason(?string $category): string
    {
        return match ($category) {
            'UNASSIGNED_ACTIVE' => 'unassigned_active_sales',
            'INACTIVE' => 'inactive_sales',
            'ANONYMIZED' => 'anonymized_sales',
            'LEGACY_ONLY' => 'legacy_only_sales',
            'DUPLICATE_DATA_ISSUE' => 'duplicate_sales_data_issue',
            'OTHER' => 'other_sales_exception',
            default => 'sales_accounting_category_missing',
        };
    }

    private function isLegacyTeam(Team $team): bool
    {
        return str_starts_with((string) $team->name, 'Migrasi Tim') || str_starts_with((string) $team->code, 'SRC-TEAM-BRANCH-');
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
