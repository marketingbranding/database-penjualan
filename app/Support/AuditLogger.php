<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\BiChecking;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lead;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\PsjbUtjCase;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\Models\SalesCaseChangeRequest;
use App\Models\SalesCaseStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function record(
        Model $subject,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
    ): void {
        if (! Auth::check()) {
            return;
        }

        self::recordAs(Auth::user(), $subject, $event, $oldValues, $newValues, $description);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function recordAs(
        User $actor,
        Model $subject,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
    ): void {
        ActivityLog::query()->create([
            'user_id' => $actor->getKey(),
            'branch_id' => self::resolveBranchId($subject),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'description' => $description ?? class_basename($subject).' #'.$subject->getKey().' '.$event,
            'old_values' => self::sanitize($oldValues),
            'new_values' => self::sanitize($newValues),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /** @param array<string, mixed> $values */
    private static function sanitize(array $values): array
    {
        return collect($values)
            ->except(['password', 'remember_token', 'updated_at', 'created_at'])
            ->all();
    }

    private static function resolveBranchId(Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Branch => $subject->getKey(),
            $subject instanceof Project => $subject->branch_id,
            $subject instanceof ProjectBlock => $subject->project()->value('branch_id'),
            $subject instanceof Lot => $subject->project()->value('branch_id'),
            $subject instanceof SalesCase => $subject->lot?->project?->branch_id
                ?? $subject->lot()->with('project')->first()?->project?->branch_id,
            $subject instanceof PsjbUtjCase => $subject->salesCase?->lot?->project?->branch_id
                ?? $subject->salesCase()->with('lot.project')->first()?->lot?->project?->branch_id,
            $subject instanceof SalesCaseChangeRequest => $subject->salesCase?->lot?->project?->branch_id
                ?? $subject->salesCase()->with('lot.project')->first()?->lot?->project?->branch_id,
            $subject instanceof BiChecking => $subject->salesCase?->lot?->project?->branch_id
                ?? $subject->salesCase()->with('lot.project')->first()?->lot?->project?->branch_id,
            $subject instanceof SalesCaseStatusHistory => $subject->salesCase?->lot?->project?->branch_id
                ?? $subject->salesCase()->with('lot.project')->first()?->lot?->project?->branch_id,
            $subject instanceof SalesAgenda => $subject->branch_id,
            $subject instanceof Lead => $subject->branch_id,
            $subject instanceof User => $subject->branch_id,
            $subject instanceof Consumer => $subject->salesCases()
                ->join('lots', 'sales_cases.lot_id', '=', 'lots.id')
                ->join('projects', 'lots.project_id', '=', 'projects.id')
                ->value('projects.branch_id') ?? Auth::user()?->branch_id,
            default => Auth::user()?->branch_id,
        };
    }
}
