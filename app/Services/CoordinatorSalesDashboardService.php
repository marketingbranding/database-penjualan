<?php

namespace App\Services;

use App\LotStatus;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoordinatorSalesDashboardService
{
    public function __construct(
        private LeadMovementService $leadMovementService,
        private SalesLeadTargetService $leadTargets,
        private SalesDailyPlanService $dailyPlans,
        private DailyAgendaPlanner $planner,
    ) {}

    public function isCoordinator(User $user): bool
    {
        if ($user->canAccessAllBranches()) {
            return false;
        }

        $teamIds = Team::query()->where('is_active', true)->where('coordinator_id', $user->getKey())->pluck('id');
        $role = Role::query()->whereIn('team_id', $teamIds)->where('name', 'koordinator_sales')->pluck('id');

        return $role->isNotEmpty() && DB::table(config('permission.table_names.model_has_roles'))
            ->whereIn('role_id', $role)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->exists();
    }

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $team = $this->activeTeam($user);

        if ($team === null) {
            return $this->emptyDashboard($user);
        }

        $sales = $this->salesInTeam($team);
        $salesIds = $sales->modelKeys();
        $today = BusinessTime::now()->toDateString();
        $month = BusinessTime::now();
        $lastSevenStart = $month->subDays(6)->toDateString();
        $leadScope = Lead::query()->where('team_id', $team->getKey());

        $stageRows = (clone $leadScope)
            ->select('sales_pic_id', 'status_lead')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('sales_pic_id', 'status_lead')
            ->get();
        $overdueRows = (clone $leadScope)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', BusinessTime::now()->utc())
            ->whereNotIn('status_lead', ['Akad', 'Tidak Lolos BI Checking'])
            ->select('sales_pic_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('sales_pic_id')
            ->get()
            ->keyBy('sales_pic_id');
        $actionMetrics = (clone $leadScope)
            ->selectRaw('SUM(CASE WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at < ? AND status_lead NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue_count', [BusinessTime::now()->utc(), 'Akad', 'Tidak Lolos BI Checking'])
            ->selectRaw('SUM(CASE WHEN status_lead = ? AND tanggal_lead BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM lead_activities recent_lead_activity WHERE recent_lead_activity.lead_id = leads.id AND recent_lead_activity.activity_type = ?) AND NOT EXISTS (SELECT 1 FROM lead_activities responded_lead_activity WHERE responded_lead_activity.lead_id = leads.id AND responded_lead_activity.activity_type <> ?) THEN 1 ELSE 0 END) as new_unresponded_count', ['No Respon', $lastSevenStart, $today, 'Lead Baru', 'Lead Baru'])
            ->first();

        $todayAgendaRows = SalesAgenda::query()
            ->where('team_id', $team->getKey())
            ->whereDate('agenda_date', $today)
            ->whereIn('status', ['planned', 'done'])
            ->select('owner_id', 'status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('owner_id', 'status')
            ->get();
        $todayAgendas = SalesAgenda::query()
            ->where('team_id', $team->getKey())
            ->whereDate('agenda_date', $today)
            ->whereIn('status', ['planned', 'done'])
            ->with(['owner', 'lead', 'salesCase.consumer', 'salesCase.lot.project', 'evidence'])
            ->orderBy('status')
            ->orderBy('id')
            ->get();
        $movementRows = $this->leadMovementService->rows($user, [
            'branch_id' => $team->branch_id,
            'date_from' => $month->startOfMonth()->toDateString(),
            'date_to' => $month->endOfMonth()->toDateString(),
        ], [$team->getKey()]);

        $salesRows = $sales->map(function (User $salesUser) use ($stageRows, $overdueRows, $todayAgendaRows, $movementRows, $team, $today, $month): array {
            $salesStages = $stageRows->where('sales_pic_id', $salesUser->getKey())->keyBy('status_lead');
            $agendaRows = $todayAgendaRows->where('owner_id', $salesUser->getKey());
            $agendaTotal = (int) $agendaRows->sum('total');
            $agendaDone = (int) ($agendaRows->firstWhere('status', 'done')->total ?? 0);
            $stages = $this->stageCounts($salesStages);
            $dailyPlan = $this->dailyPlans->forSales($salesUser, $today, $team)->load('agendas');
            $dailyPlanMinutes = $this->planner->effectiveMinutes($dailyPlan);
            $leadTarget = $this->leadTargets->progress($salesUser, $month);

            return [
                'user' => $salesUser,
                'active_leads' => (int) collect($stages)->except(['Akad', 'Tidak Lolos BI Checking'])->sum(),
                'overdue' => (int) ($overdueRows[$salesUser->getKey()]->total ?? 0),
                'agenda_total' => $agendaTotal,
                'agenda_done' => $agendaDone,
                'agenda_unfinished' => max(0, $agendaTotal - $agendaDone),
                'daily_plan' => $dailyPlan,
                'daily_plan_minutes' => $dailyPlanMinutes,
                'daily_plan_status' => $dailyPlan->status,
                'lead_target' => $leadTarget,
                'movement_utj' => (int) $movementRows->where('sales_pic_id', $salesUser->getKey())->where('status_to', 'UTJ')->sum('total'),
                'movement_akad' => (int) $movementRows->where('sales_pic_id', $salesUser->getKey())->where('status_to', 'Akad')->sum('total'),
                'stages' => $stages,
            ];
        })->sort(function (array $left, array $right): int {
            return [$right['overdue'], $right['agenda_unfinished'], $left['user']->name] <=> [$left['overdue'], $left['agenda_unfinished'], $right['user']->name];
        })->values();

        $teamStages = $this->stageCounts($stageRows->groupBy('status_lead')->map(fn (Collection $rows): object => (object) ['total' => $rows->sum('total')]));
        $teamAgendaTotal = (int) $todayAgendaRows->sum('total');
        $teamAgendaDone = (int) $todayAgendaRows->where('status', 'done')->sum('total');
        $project = $team->project_id ? Project::query()->with('lots')->find($team->project_id) : null;

        return [
            'coordinatorSalesDashboard' => true,
            'team' => $team,
            'header' => [
                'date' => $month->locale('id')->translatedFormat('l, d F Y'),
                'team' => $team->name,
                'salesCount' => $sales->count(),
                'greeting' => $this->greeting($month).', '.str($user->name)->before(' '),
            ],
            'metrics' => [
                'overdue' => (int) ($actionMetrics->overdue_count ?? 0),
                'agendaDone' => $teamAgendaDone,
                'agendaTotal' => $teamAgendaTotal,
                'newUnresponded' => (int) ($actionMetrics->new_unresponded_count ?? 0),
                'movementUtj' => (int) $movementRows->where('status_to', 'UTJ')->sum('total'),
                'movementAkad' => (int) $movementRows->where('status_to', 'Akad')->sum('total'),
            ],
            'salesRows' => $salesRows->all(),
            'teamAgendas' => $todayAgendas->map(fn (SalesAgenda $agenda): array => [
                'agenda' => $agenda,
                'isRedirectable' => $agenda->status === 'planned' && $agenda->evidence->isEmpty(),
            ])->all(),
            'pipeline' => $teamStages,
            'stock' => $project ? $this->stock($project) : null,
            'date' => $today,
        ];
    }

    private function activeTeam(User $user): ?Team
    {
        $teams = $user->teams()->where('teams.is_active', true)->where('teams.coordinator_id', $user->getKey())->with(['branch', 'project'])->get();
        $requested = (int) request()->session()->get('active_team_id');

        return $teams->firstWhere('id', $requested)
            ?? $teams->first(fn (Team $team): bool => (bool) $team->pivot?->is_default)
            ?? $teams->first();
    }

    /** @return Collection<int, User> */
    private function salesInTeam(Team $team): Collection
    {
        $roleId = Role::query()->where('team_id', $team->getKey())->where('name', 'sales')->value('id');

        if ($roleId === null) {
            return new Collection;
        }

        $salesIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $roleId)
            ->where('model_type', (new User)->getMorphClass())
            ->pluck('model_id');

        return $team->users()->whereIn('users.id', $salesIds)->orderBy('users.name')->get();
    }

    /** @param Collection<string, object> $rows */
    private function stageCounts(Collection $rows): array
    {
        return collect(Lead::STATUSES)->mapWithKeys(fn (string $status): array => [$status => (int) ($rows[$status]->total ?? 0)])->all();
    }

    /** @return array{available: int, reserved: int, sold: int, project: Project} */
    private function stock(Project $project): array
    {
        $counts = collect(LotStatus::cases())->mapWithKeys(fn (LotStatus $status): array => [$status->value => $project->lots->where('status', $status)->count()]);

        return ['project' => $project, 'available' => $counts[LotStatus::Available->value], 'reserved' => $counts[LotStatus::Reserved->value], 'sold' => $counts[LotStatus::Sold->value]];
    }

    private function greeting(CarbonImmutable $date): string
    {
        return match (true) {
            $date->hour < 11 => 'Selamat pagi',
            $date->hour < 15 => 'Selamat siang',
            $date->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /** @return array<string, mixed> */
    private function emptyDashboard(User $user): array
    {
        $now = BusinessTime::now();

        return [
            'coordinatorSalesDashboard' => true,
            'team' => null,
            'header' => ['date' => $now->locale('id')->translatedFormat('l, d F Y'), 'team' => 'Belum ada tim aktif', 'salesCount' => 0, 'greeting' => $this->greeting($now).', '.str($user->name)->before(' ')],
            'metrics' => ['overdue' => 0, 'agendaDone' => 0, 'agendaTotal' => 0, 'newUnresponded' => 0, 'movementUtj' => 0, 'movementAkad' => 0],
            'salesRows' => [], 'teamAgendas' => [], 'pipeline' => collect(Lead::STATUSES)->mapWithKeys(fn (string $status): array => [$status => 0])->all(), 'stock' => null, 'date' => $now->toDateString(),
        ];
    }
}
