<?php

namespace App\Services;

use App\Models\LeadActivity;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class LeadMovementService
{
    /**
     * @param  array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string}  $filters
     * @param  array<int, int>  $teamIds
     * @return Collection<int, object>
     */
    public function rows(User $viewer, array $filters, array $teamIds): Collection
    {
        return LeadActivity::query()
            ->join('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->where('lead_activities.activity_type', 'Status Update')
            ->whereIn('lead_activities.status_to', ['UTJ', 'Akad'])
            ->whereBetween('lead_activities.happened_at', [
                BusinessTime::utcStartOfDay($filters['date_from']),
                BusinessTime::utcEndOfDay($filters['date_to']),
            ])
            ->when(! $viewer->canAccessAllBranches(), fn (Builder $query) => $query->where('leads.branch_id', $viewer->branch_id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('leads.branch_id', $branchId))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('leads.project_id', $projectId))
            ->when($filters['coordinator_id'] ?? null, fn (Builder $query, int $coordinatorId) => $query->whereExists(fn (QueryBuilder $team) => $team
                ->selectRaw('1')
                ->from('teams')
                ->whereColumn('teams.id', 'leads.team_id')
                ->where('teams.coordinator_id', $coordinatorId)))
            ->whereIn('leads.team_id', $teamIds)
            ->select('leads.team_id', 'leads.sales_pic_id', 'lead_activities.status_to')
            ->selectRaw('COUNT(DISTINCT lead_activities.lead_id) as total')
            ->groupBy('leads.team_id', 'leads.sales_pic_id', 'lead_activities.status_to')
            ->get();
    }
}
