<?php

namespace App\Http\Controllers;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Lot;
use App\Models\Project;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\PaymentMethod;
use App\Services\CoordinatorSalesDashboardService;
use App\Services\PersonalSalesDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private PersonalSalesDashboardService $personalSalesDashboard,
        private CoordinatorSalesDashboardService $coordinatorSalesDashboard,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($this->personalSalesDashboard->isPersonalSales($user)) {
            return view('dashboard-personal', $this->personalSalesDashboard->build($user));
        }

        if ($this->coordinatorSalesDashboard->isCoordinator($user)) {
            return view('dashboard-coordinator', $this->coordinatorSalesDashboard->build($user));
        }

        $branchId = $user->canAccessAllBranches()
            ? ($request->integer('branch_id') ?: null)
            : $user->branch_id;

        $lots = Lot::query()
            ->when($branchId, fn ($query) => $query->whereHas('project', fn ($project) => $project->where('branch_id', $branchId)))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'));
        $salesCases = SalesCase::query()
            ->active()
            ->when($branchId, fn ($query) => $query->whereHas('lot.project', fn ($project) => $project->where('branch_id', $branchId)))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'));
        $leads = $this->leadScope(Lead::query(), $user, $branchId);
        $agendas = $this->agendaScope(SalesAgenda::query(), $user, $branchId);
        $recentLeadActivities = (clone $leads)
            ->with(['latestActivity.user'])
            ->whereHas('activities')
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (Lead $lead): array => [
                'label' => 'Lead · '.$lead->nama_konsumen,
                'description' => $lead->latestActivity?->description ?? $lead->status_lead,
                'date' => $lead->latestActivity?->happened_at ?? $lead->updated_at,
                'url' => route('leads.show', $lead),
                'accent' => 'terracotta',
            ]);
        $recentAgendaActivities = (clone $agendas)
            ->with(['creator', 'owner'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (SalesAgenda $agenda): array => [
                'label' => 'Agenda · '.$agenda->title,
                'description' => $agenda->activity_result ?: ucfirst($agenda->status),
                'date' => $agenda->updated_at,
                'url' => route('sales-agendas.edit', $agenda),
                'accent' => 'gold',
            ]);

        return view('dashboard', [
            'branchId' => $branchId,
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'metrics' => [
                'consumers' => (clone $salesCases)->distinct('consumer_id')->count('consumer_id'),
                'availableLots' => (clone $lots)->where('status', LotStatus::Available->value)->count(),
                'reservedLots' => (clone $lots)->where('status', LotStatus::Reserved->value)->count(),
                'soldLots' => (clone $lots)->where('status', LotStatus::Sold->value)->count(),
                'kpr' => (clone $salesCases)->where('payment_method', PaymentMethod::Kpr->value)->count(),
                'cash' => (clone $salesCases)->where('payment_method', PaymentMethod::Cash->value)->count(),
                'leads' => (clone $leads)->count(),
                'plannedAgendas' => (clone $agendas)->where('status', 'planned')->whereDate('agenda_date', '>=', today())->count(),
                'completedAgendas' => (clone $agendas)->where('status', 'done')->whereDate('agenda_date', '>=', today()->startOfMonth())->count(),
            ],
            'projects' => Project::query()
                ->withCount(['lots', 'salesCases'])
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'recentSalesCases' => (clone $salesCases)
                ->with(['consumer', 'lot.project'])
                ->latest()
                ->limit(8)
                ->get(),
            'upcomingAgendas' => (clone $agendas)
                ->with(['lead', 'salesCase.consumer'])
                ->where('status', 'planned')
                ->whereDate('agenda_date', '>=', today())
                ->orderBy('agenda_date')
                ->limit(6)
                ->get(),
            'recentLeads' => (clone $leads)
                ->with(['salesPic', 'project'])
                ->latest('tanggal_lead')
                ->limit(6)
                ->get(),
            'activityFeed' => $recentLeadActivities->concat($recentAgendaActivities)
                ->sortByDesc('date')
                ->values()
                ->take(8),
        ]);
    }

    private function leadScope($query, $user, ?int $branchId)
    {
        return $query
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('leads.view-all'), function ($query) use ($user): void {
                if ($user->can('leads.view-branch') || $user->can('leads.monitor')) {
                    return;
                }

                if ($user->can('leads.view-team')) {
                    $query->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                    return;
                }

                $query->where('sales_pic_id', $user->getKey());
            });
    }

    private function agendaScope($query, $user, ?int $branchId)
    {
        return $query
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('sales.view-all'), function ($query) use ($user): void {
                if ($user->can('leads.view-branch') || $user->can('leads.monitor')) {
                    return;
                }

                if ($user->can('sales.view-team')) {
                    $query->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                    return;
                }

                $query->where('owner_id', $user->getKey());
            });
    }
}
