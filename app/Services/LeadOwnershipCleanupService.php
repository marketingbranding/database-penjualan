<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadCutoverDisposition;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class LeadOwnershipCleanupService
{
    /**
     * Audit all Leads, and optionally apply only deterministic repairs/dispositions.
     *
     * @return array<string, mixed>
     */
    public function run(CarbonImmutable $cutoverAt, string $timezone, bool $apply = false): array
    {
        $cutoverAt = $cutoverAt->setTimezone($timezone);
        $currentMonthBoundary = $cutoverAt->startOfMonth();
        $report = [
            'format_version' => 1,
            'mode' => $apply ? 'apply' : 'dry-run',
            'cutover_at' => $cutoverAt->toIso8601String(),
            'timezone' => $timezone,
            'current_month_boundary' => $currentMonthBoundary->toIso8601String(),
            'counts' => [
                'leads_inspected' => 0,
                'branch_team_mismatches' => 0,
                'valid_sales_repairable' => 0,
                'recovered_sales_owner' => 0,
                'confirmed_recovered_sales' => 0,
                'high_confidence_recommendations' => 0,
                'needs_manual_assignment_current_month' => 0,
                'exclude_from_cutover_old_unresolved' => 0,
                'other_blocker' => 0,
                'deterministic_repairs' => 0,
                'changed' => 0,
                'unchanged' => 0,
            ],
            'classifications' => [],
            'safety' => [
                'source_records_deleted' => false,
                'production_writes_performed' => $apply && app()->environment('production'),
            ],
        ];

        $salesCache = [];
        $leads = Lead::withoutGlobalScopes()
            ->with([
                'salesPic.roles', 'creator.roles', 'team', 'project',
                'activities.user.roles',
                'handoffs.requester.roles', 'handoffs.reviewer.roles',
                'originAgenda.owner.roles',
            ])
            ->orderBy('id')
            ->get();

        $operation = function () use (&$report, $leads, $cutoverAt, $currentMonthBoundary, $apply, &$salesCache): void {
            foreach ($leads as $lead) {
                $report['counts']['leads_inspected']++;
                $classification = $this->classify($lead, $currentMonthBoundary, $salesCache);

                if ($classification === null) {
                    continue;
                }

                $report['counts']['branch_team_mismatches'] += (int) $classification['is_branch_team_mismatch'];
                $report['counts'][$this->countKey((string) $classification['classification'])]++;
                if ($classification['evidence_confidence'] === 'CONFIRMED') {
                    $report['counts']['confirmed_recovered_sales']++;
                }
                if ($classification['evidence_confidence'] === 'HIGH CONFIDENCE') {
                    $report['counts']['high_confidence_recommendations']++;
                }
                if ($classification['deterministic_repair']) {
                    $report['counts']['deterministic_repairs']++;
                }

                $report['classifications'][] = $classification['report'];

                if (! $apply) {
                    continue;
                }

                $changed = $this->applyClassification($classification, $lead, $cutoverAt, $currentMonthBoundary);
                $report['counts'][$changed ? 'changed' : 'unchanged']++;
            }
        };

        if ($apply) {
            DB::transaction($operation);
        } else {
            $operation();
        }

        return $report;
    }

    /**
     * @param  array<int, string>  $salesCache
     * @return array<string, mixed>|null
     */
    private function classify(Lead $lead, CarbonImmutable $currentMonthBoundary, array &$salesCache): ?array
    {
        $salesPic = $lead->salesPic;
        if ($salesPic === null) {
            return $this->otherBlocker($lead, 'Lead tidak memiliki Sales PIC.');
        }

        if ($this->isSales($salesPic, $salesCache)) {
            $finalTeams = $this->finalSalesTeams($salesPic);
            $finalTeam = count($finalTeams) === 1 ? $finalTeams[0] : null;
            $isMismatch = $lead->branch_id !== $salesPic->branch_id
                || $lead->team?->branch_id !== $salesPic->branch_id
                || ($finalTeam !== null && $lead->team_id !== $finalTeam->getKey());

            if (! $isMismatch) {
                return null;
            }

            if ($finalTeam === null) {
                return $this->otherBlocker($lead, 'Sales PIC valid, tetapi final Team tidak dapat diperbaiki deterministic.', [
                    'is_branch_team_mismatch' => true,
                    'sales_branch_id' => $salesPic->branch_id,
                    'final_team_ids' => collect($finalTeams)->map(fn (Team $team): int => $team->getKey())->values()->all(),
                ]);
            }

            if ($lead->project?->branch_id !== null && $lead->project->branch_id !== $salesPic->branch_id) {
                return $this->otherBlocker($lead, 'Project Branch berbeda dari Sales PIC; Branch Lead tidak diubah tanpa business review.', [
                    'is_branch_team_mismatch' => true,
                    'project_branch_id' => $lead->project->branch_id,
                    'sales_branch_id' => $salesPic->branch_id,
                ]);
            }

            return $this->classification(
                $lead,
                LeadCutoverDisposition::VALID_SALES_REPAIRABLE,
                'CONFIRMED',
                $salesPic,
                $finalTeam,
                true,
                'Branch dan Team mengikuti Sales PIC serta final Team authoritative.',
            );
        }

        $evidence = $this->ownershipEvidence($lead, $salesCache);
        $candidateIds = array_keys($evidence['candidates']);
        if (count($candidateIds) === 1) {
            $candidate = $evidence['candidates'][(int) $candidateIds[0]];
            $candidateTeams = $this->finalSalesTeams($candidate);
            $candidateTeam = count($candidateTeams) === 1 ? $candidateTeams[0] : null;
            $confidence = count($evidence['signals'][(int) $candidateIds[0]]) >= 2 ? 'CONFIRMED' : 'HIGH CONFIDENCE';

            return $this->classification(
                $lead,
                LeadCutoverDisposition::RECOVERED_SALES_OWNER,
                $confidence,
                $candidate,
                $candidateTeam,
                $candidateTeam !== null && $confidence === 'CONFIRMED',
                $confidence === 'CONFIRMED'
                    ? 'Sales owner recovered from independent historical ownership evidence.'
                    : 'One Sales owner is suggested by historical evidence, but requires review before apply.',
                ['ownership_evidence' => $evidence['signals'][(int) $candidateIds[0]]],
            );
        }

        $isCurrentMonth = $lead->created_at !== null
            && CarbonImmutable::instance($lead->created_at)->setTimezone($currentMonthBoundary->getTimezone())->greaterThanOrEqualTo($currentMonthBoundary);
        $classification = $isCurrentMonth
            ? LeadCutoverDisposition::NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH
            : LeadCutoverDisposition::EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED;
        $reason = count($candidateIds) > 1
            ? 'Multiple Sales candidates found; ownership is ambiguous.'
            : 'No trustworthy Sales ownership evidence found.';

        return $this->classification(
            $lead,
            $classification,
            count($candidateIds) > 1 ? 'AMBIGUOUS' : 'NONE',
            null,
            null,
            false,
            $reason,
            ['ownership_evidence' => $evidence['signals']],
        );
    }

    /** @param array<int, string> $salesCache */
    private function isSales(User $user, array &$salesCache): bool
    {
        if (array_key_exists($user->getKey(), $salesCache)) {
            return $salesCache[$user->getKey()] === 'sales';
        }

        $salesCache[$user->getKey()] = DB::table(config('permission.table_names.model_has_roles').' as model_roles')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'model_roles.role_id')
            ->join('teams', 'teams.id', '=', 'roles.team_id')
            ->where('model_roles.model_type', $user->getMorphClass())
            ->where('model_roles.model_id', $user->getKey())
            ->where('roles.name', 'sales')
            ->where('teams.is_active', true)
            ->exists() ? 'sales' : 'other';

        return $salesCache[$user->getKey()] === 'sales';
    }

    /** @return list<Team> */
    private function finalSalesTeams(User $sales): array
    {
        return Team::query()
            ->where('teams.is_active', true)
            ->where('teams.branch_id', $sales->branch_id)
            ->where('teams.code', 'like', 'COORD-B%')
            ->whereExists(function (QueryBuilder $query) use ($sales): void {
                $query->selectRaw('1')
                    ->from('model_has_roles as model_roles')
                    ->join('roles', 'roles.id', '=', 'model_roles.role_id')
                    ->whereColumn('model_roles.team_id', 'teams.id')
                    ->where('model_roles.model_type', $sales->getMorphClass())
                    ->where('model_roles.model_id', $sales->getKey())
                    ->where('roles.name', 'sales');
            })
            ->orderBy('teams.id')
            ->get()
            ->all();
    }

    /**
     * @param  array<int, string>  $salesCache
     * @return array{candidates: array<int, User>, signals: array<int, list<string>>}
     */
    private function ownershipEvidence(Lead $lead, array &$salesCache): array
    {
        $candidates = [];
        $signals = [];
        $add = function (?User $user, string $signal) use (&$candidates, &$signals, &$salesCache): void {
            if ($user === null || ! $this->isSales($user, $salesCache)) {
                return;
            }

            $id = $user->getKey();
            $candidates[$id] = $user;
            $signals[$id] ??= [];
            if (! in_array($signal, $signals[$id], true)) {
                $signals[$id][] = $signal;
            }
        };

        $add($lead->creator, 'creator');
        foreach ($lead->activities as $activity) {
            $add($activity->user, 'activity');
        }
        foreach ($lead->handoffs as $handoff) {
            $add($handoff->requester, 'handoff_requester');
            $add($handoff->reviewer, 'handoff_reviewer');
        }
        $add($lead->originAgenda?->owner, 'agenda_owner');

        return ['candidates' => $candidates, 'signals' => $signals];
    }

    /** @param array<string, mixed> $extra */
    private function otherBlocker(Lead $lead, string $reason, array $extra = []): array
    {
        return $this->classification($lead, LeadCutoverDisposition::OTHER_BLOCKER, 'NONE', null, null, false, $reason, $extra);
    }

    /** @param array<string, mixed> $extra */
    private function classification(
        Lead $lead,
        string $classification,
        string $confidence,
        ?User $newSalesPic,
        ?Team $newTeam,
        bool $deterministicRepair,
        string $reason,
        array $extra = [],
    ): array {
        $isMismatch = (bool) ($extra['is_branch_team_mismatch'] ?? (
            $classification === LeadCutoverDisposition::VALID_SALES_REPAIRABLE
            || $lead->sales_pic_id !== $newSalesPic?->getKey()
        ));
        $report = [
            'lead_id' => $lead->getKey(),
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
            'current_sales_pic_id' => $lead->sales_pic_id,
            'current_sales_pic_role' => $this->roleLabel($lead->salesPic),
            'created_by' => $lead->created_by,
            'status_lead' => $lead->status_lead,
            'project_id' => $lead->project_id,
            'lead_branch_id' => $lead->branch_id,
            'lead_team_id' => $lead->team_id,
            'lead_team_branch_id' => $lead->team?->branch_id,
            'sales_pic_branch_id' => $lead->salesPic?->branch_id,
            'classification' => $classification,
            'evidence_confidence' => $confidence,
            'new_sales_pic_id' => $newSalesPic?->getKey(),
            'new_team_id' => $newTeam?->getKey(),
            'new_team_branch_id' => $newTeam?->branch_id,
            'deterministic_repair' => $deterministicRepair,
            'proposed_action' => $this->proposedAction($classification, $confidence, $deterministicRepair),
            'reason' => $reason,
            ...$extra,
        ];

        return [
            'classification' => $classification,
            'evidence_confidence' => $confidence,
            'new_sales_pic' => $newSalesPic,
            'new_team' => $newTeam,
            'deterministic_repair' => $deterministicRepair,
            'is_branch_team_mismatch' => $isMismatch,
            'reason' => $reason,
            'report' => $report,
        ];
    }

    private function roleLabel(?User $user): ?string
    {
        return $user === null ? null : ($user->roles->pluck('name')->join(',') ?: 'unknown');
    }

    private function proposedAction(string $classification, string $confidence, bool $deterministicRepair): string
    {
        return match ($classification) {
            LeadCutoverDisposition::VALID_SALES_REPAIRABLE => $deterministicRepair ? 'REPAIR_BRANCH_AND_TEAM' : 'REVIEW',
            LeadCutoverDisposition::RECOVERED_SALES_OWNER => $confidence === 'CONFIRMED' && $deterministicRepair ? 'REPAIR_SALES_PIC_AND_TEAM' : 'MANUAL_REVIEW',
            LeadCutoverDisposition::NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH => 'MANUAL_ASSIGNMENT_REQUIRED',
            LeadCutoverDisposition::EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED => 'EXCLUDE_FROM_OPERATIONAL_CUTOVER',
            default => 'MANUAL_REVIEW',
        };
    }

    private function countKey(string $classification): string
    {
        return match ($classification) {
            LeadCutoverDisposition::VALID_SALES_REPAIRABLE => 'valid_sales_repairable',
            LeadCutoverDisposition::RECOVERED_SALES_OWNER => 'recovered_sales_owner',
            LeadCutoverDisposition::NEEDS_MANUAL_ASSIGNMENT_CURRENT_MONTH => 'needs_manual_assignment_current_month',
            LeadCutoverDisposition::EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED => 'exclude_from_cutover_old_unresolved',
            default => 'other_blocker',
        };
    }

    /** @param array<string, mixed> $classification */
    private function applyClassification(array $classification, Lead $lead, CarbonImmutable $cutoverAt, CarbonImmutable $currentMonthBoundary): bool
    {
        $newSalesPic = $classification['new_sales_pic'];
        $newTeam = $classification['new_team'];

        if ($classification['classification'] === LeadCutoverDisposition::VALID_SALES_REPAIRABLE
            || ($classification['classification'] === LeadCutoverDisposition::RECOVERED_SALES_OWNER
                && $classification['evidence_confidence'] === 'CONFIRMED'
                && $classification['deterministic_repair'])) {
            $attributes = [
                'branch_id' => $newSalesPic->branch_id,
                'team_id' => $newTeam->getKey(),
            ];
            if ($classification['classification'] === LeadCutoverDisposition::RECOVERED_SALES_OWNER) {
                $attributes['sales_pic_id'] = $newSalesPic->getKey();
            }
            $changed = $lead->branch_id !== $attributes['branch_id']
                || $lead->team_id !== $attributes['team_id']
                || (isset($attributes['sales_pic_id']) && $lead->sales_pic_id !== $attributes['sales_pic_id']);
            if ($changed) {
                DB::table('leads')->where('id', $lead->getKey())->update($attributes);
            }
            LeadCutoverDisposition::query()->where('lead_id', $lead->getKey())->delete();

            return $changed;
        }

        $values = [
            'classification' => $classification['classification'],
            'evidence_confidence' => $classification['evidence_confidence'],
            'cutover_at' => $cutoverAt,
            'current_month_boundary' => $currentMonthBoundary,
            'old_sales_pic_id' => $lead->sales_pic_id,
            'new_sales_pic_id' => $newSalesPic?->getKey(),
            'old_team_id' => $lead->team_id,
            'new_team_id' => $newTeam?->getKey(),
            'evidence' => $classification['report'],
            'reason' => $classification['reason'],
            'applied_at' => now(),
        ];
        $existing = LeadCutoverDisposition::query()->where('lead_id', $lead->getKey())->first();
        if ($existing !== null) {
            $existingValues = $existing->only([
                'classification', 'evidence_confidence', 'cutover_at', 'current_month_boundary',
                'old_sales_pic_id', 'new_sales_pic_id', 'old_team_id', 'new_team_id', 'evidence', 'reason',
            ]);
            $newValues = collect($values)->except(['applied_at'])->all();
            if ($existingValues['evidence'] === json_encode($newValues['evidence'], JSON_THROW_ON_ERROR)) {
                $existingValues['evidence'] = $newValues['evidence'];
            }
            if ($existingValues == $newValues) {
                return false;
            }
        }

        $disposition = LeadCutoverDisposition::query()->updateOrCreate(['lead_id' => $lead->getKey()], $values);

        return $existing === null || $existing->getAttributes() !== $disposition->getAttributes();
    }
}
