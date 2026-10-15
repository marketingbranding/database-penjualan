<?php

namespace App;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamPerformanceService
{
    /** @var array<int, string> */
    private const FUNNEL_STATUSES = ['No Respon', 'Diskusi', 'Tatap Muka', 'Cek Lokasi', 'UTJ', 'Cek SLIK', 'Akad'];

    /** @var array<int, string> */
    private const SIDE_OUTCOMES = ['Jadi Freelance', 'Tidak Lolos BI Checking'];

    /**
     * @param  array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string}  $filters
     * @return array<string, mixed>
     */
    public function build(User $viewer, array $filters): array
    {
        $teams = $this->teams($viewer, $filters);
        $teamIds = $teams->pluck('id')->all();
        $salesUserIds = $this->salesUserIds($teamIds);
        $leadPeriod = $this->leadQuery($viewer, $filters, $teamIds, true);
        $leadScope = $this->leadQuery($viewer, $filters, $teamIds, false);
        $agendaPeriod = $this->agendaQuery($viewer, $filters, $teamIds);
        $statusRows = $this->statusRows($leadPeriod);
        $cohortRows = $this->cohortRows($leadPeriod);
        $movementRows = $this->movementRows($viewer, $filters, $teamIds);
        $overdueRows = $this->overdueRows($leadScope);
        $agendaRows = $this->agendaRows($agendaPeriod);
        $redirectRows = $this->redirectRows($viewer, $filters, $teamIds);

        $sales = $teams
            ->flatMap(fn (Team $team): Collection => $team->users
                ->whereIn('id', $salesUserIds)
                ->map(fn (User $user): array => $this->salesMetrics($team, $user, $statusRows, $cohortRows, $movementRows, $overdueRows, $agendaRows, $redirectRows)))
            ->keyBy('sales_id');

        $teamMetrics = $teams->map(function (Team $team) use ($sales): array {
            $teamSales = $sales->where('team_id', $team->getKey())->values();

            return [
                'team_id' => $team->getKey(),
                'team_name' => $team->name,
                'project_id' => $team->project_id,
                'project_name' => $team->project?->name ?? 'Tanpa proyek',
                'coordinator_name' => $team->coordinator?->name ?? '-',
                'sales' => $teamSales,
                'totals' => $this->sumMetrics($teamSales),
            ];
        });

        $projects = $teamMetrics->groupBy('project_id')->map(function (Collection $projectTeams, $projectId): array {
            return [
                'project_id' => $projectId,
                'project_name' => $projectTeams->first()['project_name'],
                'teams' => $projectTeams->values(),
                'totals' => $this->sumMetrics($projectTeams->pluck('totals')),
            ];
        })->values();

        return [
            'projects' => $projects,
            'totals' => $this->sumMetrics($teamMetrics->pluck('totals')),
            'filters' => $filters,
            'timezone' => config('app.business_timezone', 'Asia/Jakarta'),
            'statusLabels' => self::FUNNEL_STATUSES,
            'sideOutcomeLabels' => self::SIDE_OUTCOMES,
            'conversionNote' => 'Konversi dihitung dari status history hingga hari ini. Credit movement memakai Sales PIC dan tim saat ini.',
            'overdueNote' => 'Overdue follow-up dihitung per hari ini dan tidak dibatasi periode tanggal Lead.',
        ];
    }

    /** @param array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string} $filters */
    private function teams(User $viewer, array $filters): Collection
    {
        return Team::query()
            ->with(['project', 'coordinator', 'users'])
            ->where('is_active', true)
            ->when(! $viewer->canAccessAllBranches(), fn (Builder $query) => $query->where('branch_id', $viewer->branch_id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['coordinator_id'] ?? null, fn (Builder $query, int $coordinatorId) => $query->where('coordinator_id', $coordinatorId))
            ->orderBy('project_id')->orderBy('name')
            ->get();
    }

    /** @param array<int, int> $teamIds */
    private function salesUserIds(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }

        $roleIds = Role::query()->whereIn('team_id', $teamIds)->where('name', 'sales')->pluck('id');

        return DB::table(config('permission.table_names.model_has_roles'))
            ->whereIn('role_id', $roleIds)
            ->where('model_type', (new User)->getMorphClass())
            ->pluck('model_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();
    }

    /** @param array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string} $filters */
    private function leadQuery(User $viewer, array $filters, array $teamIds, bool $period): Builder
    {
        return Lead::query()
            ->when(! $viewer->canAccessAllBranches(), fn (Builder $query) => $query->where('branch_id', $viewer->branch_id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['coordinator_id'] ?? null, fn (Builder $query, int $coordinatorId) => $query->whereHas('team', fn (Builder $team) => $team->where('coordinator_id', $coordinatorId)))
            ->whereIn('team_id', $teamIds)
            ->when($period, fn (Builder $query) => $query->whereBetween('tanggal_lead', [$filters['date_from'], $filters['date_to']]));
    }

    /** @param array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string} $filters */
    private function agendaQuery(User $viewer, array $filters, array $teamIds): Builder
    {
        return SalesAgenda::query()
            ->when(! $viewer->canAccessAllBranches(), fn (Builder $query) => $query->where('branch_id', $viewer->branch_id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['project_id'] ?? null, function (Builder $query, int $projectId): void {
                $query->where(function (Builder $query) use ($projectId): void {
                    $query->whereHas('lead.project', fn (Builder $project) => $project->whereKey($projectId))
                        ->orWhereHas('salesCase.lot.project', fn (Builder $project) => $project->whereKey($projectId))
                        ->orWhereHas('team.project', fn (Builder $project) => $project->whereKey($projectId));
                });
            })
            ->when($filters['coordinator_id'] ?? null, fn (Builder $query, int $coordinatorId) => $query->whereHas('team', fn (Builder $team) => $team->where('coordinator_id', $coordinatorId)))
            ->whereIn('team_id', $teamIds)
            ->whereDate('agenda_date', '>=', $filters['date_from'])
            ->whereDate('agenda_date', '<=', $filters['date_to']);
    }

    private function statusRows(Builder $query): Collection
    {
        return $query->select('team_id', 'sales_pic_id', 'status_lead')->selectRaw('COUNT(*) as total')->groupBy('team_id', 'sales_pic_id', 'status_lead')->get();
    }

    private function cohortRows(Builder $query): Collection
    {
        $utjStatuses = ['UTJ', 'Cek SLIK', 'Akad', 'Tidak Lolos BI Checking'];
        $akadStatuses = ['Akad'];

        return $query->select('team_id', 'sales_pic_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status_lead IN (?,?,?,?) OR EXISTS (SELECT 1 FROM lead_activities la WHERE la.lead_id = leads.id AND la.activity_type = ? AND la.status_to IN (?,?,?,?)) THEN 1 ELSE 0 END) as reached_utj', [...$utjStatuses, 'Status Update', ...$utjStatuses])
            ->selectRaw('SUM(CASE WHEN status_lead = ? OR EXISTS (SELECT 1 FROM lead_activities la WHERE la.lead_id = leads.id AND la.activity_type = ? AND la.status_to = ?) THEN 1 ELSE 0 END) as reached_akad', ['Akad', 'Status Update', 'Akad'])
            ->groupBy('team_id', 'sales_pic_id')
            ->get();
    }

    /** @param array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string} $filters */
    private function movementRows(User $viewer, array $filters, array $teamIds): Collection
    {
        return LeadActivity::query()
            ->join('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->where('lead_activities.activity_type', 'Status Update')
            ->whereIn('lead_activities.status_to', ['UTJ', 'Akad'])
            ->whereBetween('lead_activities.happened_at', [BusinessTime::utcStartOfDay($filters['date_from']), BusinessTime::utcEndOfDay($filters['date_to'])])
            ->when(! $viewer->canAccessAllBranches(), fn ($query) => $query->where('leads.branch_id', $viewer->branch_id))
            ->when($filters['branch_id'] ?? null, fn ($query, int $branchId) => $query->where('leads.branch_id', $branchId))
            ->when($filters['project_id'] ?? null, fn ($query, int $projectId) => $query->where('leads.project_id', $projectId))
            ->when($filters['coordinator_id'] ?? null, fn ($query, int $coordinatorId) => $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('teams')->whereColumn('teams.id', 'leads.team_id')->where('teams.coordinator_id', $coordinatorId)))
            ->whereIn('leads.team_id', $teamIds)
            ->select('leads.team_id', 'leads.sales_pic_id', 'lead_activities.status_to')
            ->selectRaw('COUNT(DISTINCT lead_activities.lead_id) as total')
            ->groupBy('leads.team_id', 'leads.sales_pic_id', 'lead_activities.status_to')
            ->get();
    }

    private function overdueRows(Builder $query): Collection
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', BusinessTime::now()->utc())
            ->whereNotIn('status_lead', ['Akad', 'Tidak Lolos BI Checking'])
            ->select('team_id', 'sales_pic_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('team_id', 'sales_pic_id')
            ->get();
    }

    private function agendaRows(Builder $query): Collection
    {
        return $query->select('team_id', 'owner_id', 'status')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? AND agenda_date < ? THEN 1 ELSE 0 END) as overdue', ['planned', BusinessTime::now()->toDateString()])
            ->groupBy('team_id', 'owner_id', 'status')
            ->get();
    }

    /** @param array{branch_id?: int|null, project_id?: int|null, coordinator_id?: int|null, date_from: string, date_to: string} $filters */
    private function redirectRows(User $viewer, array $filters, array $teamIds): Collection
    {
        return DB::table('sales_agenda_assignment_histories as h')
            ->join('sales_agendas as a', 'a.id', '=', 'h.sales_agenda_id')
            ->where('h.action', 'redirected')
            ->whereBetween('h.created_at', [BusinessTime::utcStartOfDay($filters['date_from']), BusinessTime::utcEndOfDay($filters['date_to'])])
            ->when(! $viewer->canAccessAllBranches(), fn ($query) => $query->where('a.branch_id', $viewer->branch_id))
            ->when($filters['branch_id'] ?? null, fn ($query, int $branchId) => $query->where('a.branch_id', $branchId))
            ->when($filters['project_id'] ?? null, function ($query, int $projectId): void {
                $query->where(function ($query) use ($projectId): void {
                    $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('leads')->whereColumn('leads.id', 'a.lead_id')->where('leads.project_id', $projectId))
                        ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('sales_cases')->join('lots', 'lots.id', '=', 'sales_cases.lot_id')->whereColumn('sales_cases.id', 'a.sales_case_id')->where('lots.project_id', $projectId))
                        ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('teams')->whereColumn('teams.id', 'a.team_id')->where('teams.project_id', $projectId));
                });
            })
            ->when($filters['coordinator_id'] ?? null, fn ($query, int $coordinatorId) => $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('teams')->whereColumn('teams.id', 'a.team_id')->where('teams.coordinator_id', $coordinatorId)))
            ->whereIn('a.team_id', $teamIds)
            ->select('a.team_id', 'h.from_user_id', 'h.to_user_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('a.team_id', 'h.from_user_id', 'h.to_user_id')
            ->get();
    }

    private function salesMetrics(Team $team, User $sales, Collection $statusRows, Collection $cohortRows, Collection $movementRows, Collection $overdueRows, Collection $agendaRows, Collection $redirectRows): array
    {
        $status = $statusRows->where('team_id', $team->getKey())->where('sales_pic_id', $sales->getKey())->keyBy('status_lead');
        $cohort = $cohortRows->first(fn ($row): bool => $row->team_id === $team->getKey() && $row->sales_pic_id === $sales->getKey());
        $movement = $movementRows->where('team_id', $team->getKey())->where('sales_pic_id', $sales->getKey())->keyBy('status_to');
        $overdue = $overdueRows->first(fn ($row): bool => $row->team_id === $team->getKey() && $row->sales_pic_id === $sales->getKey());
        $agendas = $agendaRows->where('team_id', $team->getKey())->where('owner_id', $sales->getKey());
        $redirectIn = $redirectRows->where('team_id', $team->getKey())->where('to_user_id', $sales->getKey())->sum('total');
        $redirectOut = $redirectRows->where('team_id', $team->getKey())->where('from_user_id', $sales->getKey())->sum('total');
        $planned = (int) $agendas->where('status', 'planned')->sum('total');
        $done = (int) $agendas->where('status', 'done')->sum('total');
        $cancelled = (int) $agendas->where('status', 'cancelled')->sum('total');
        $overdueAgendas = (int) $agendas->sum('overdue');

        return [
            'sales_id' => $sales->getKey(),
            'sales_name' => $sales->name,
            'team_id' => $team->getKey(),
            'team_name' => $team->name,
            'project_id' => $team->project_id,
            'project_name' => $team->project?->name ?? 'Tanpa proyek',
            'lead_total' => (int) $status->sum('total'),
            'statuses' => collect(self::FUNNEL_STATUSES)->mapWithKeys(fn (string $label): array => [$label => (int) ($status[$label]->total ?? 0)])->all(),
            'side_outcomes' => collect(self::SIDE_OUTCOMES)->mapWithKeys(fn (string $label): array => [$label => (int) ($status[$label]->total ?? 0)])->all(),
            'movement_utj' => (int) ($movement['UTJ']->total ?? 0),
            'movement_akad' => (int) ($movement['Akad']->total ?? 0),
            'cohort_total' => (int) ($cohort->total ?? 0),
            'cohort_reached_utj' => (int) ($cohort->reached_utj ?? 0),
            'cohort_reached_akad' => (int) ($cohort->reached_akad ?? 0),
            'overdue_followups' => (int) ($overdue->total ?? 0),
            'agendas_planned' => $planned,
            'agendas_done' => $done,
            'agendas_cancelled' => $cancelled,
            'agendas_overdue' => $overdueAgendas,
            'completion_rate' => ($done + $overdueAgendas) > 0 ? round($done / ($done + $overdueAgendas) * 100, 1) : 0.0,
            'redirected_in' => (int) $redirectIn,
            'redirected_out' => (int) $redirectOut,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function sumMetrics(Collection $items): array
    {
        $first = $items->first();
        $statuses = collect(self::FUNNEL_STATUSES)->mapWithKeys(fn (string $label): array => [$label => $items->sum(fn (array $item): int => (int) ($item['statuses'][$label] ?? 0))])->all();
        $sideOutcomes = collect(self::SIDE_OUTCOMES)->mapWithKeys(fn (string $label): array => [$label => $items->sum(fn (array $item): int => (int) ($item['side_outcomes'][$label] ?? 0))])->all();
        $done = (int) $items->sum('agendas_done');
        $overdue = (int) $items->sum('agendas_overdue');

        return [
            'lead_total' => (int) $items->sum('lead_total'),
            'statuses' => $statuses,
            'side_outcomes' => $sideOutcomes,
            'movement_utj' => (int) $items->sum('movement_utj'),
            'movement_akad' => (int) $items->sum('movement_akad'),
            'cohort_total' => (int) $items->sum('cohort_total'),
            'cohort_reached_utj' => (int) $items->sum('cohort_reached_utj'),
            'cohort_reached_akad' => (int) $items->sum('cohort_reached_akad'),
            'overdue_followups' => (int) $items->sum('overdue_followups'),
            'agendas_planned' => (int) $items->sum('agendas_planned'),
            'agendas_done' => $done,
            'agendas_cancelled' => (int) $items->sum('agendas_cancelled'),
            'agendas_overdue' => $overdue,
            'completion_rate' => ($done + $overdue) > 0 ? round($done / ($done + $overdue) * 100, 1) : 0.0,
            'redirected_in' => (int) $items->sum('redirected_in'),
            'redirected_out' => (int) $items->sum('redirected_out'),
            'project_id' => $first['project_id'] ?? null,
        ];
    }
}
