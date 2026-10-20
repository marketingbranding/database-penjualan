<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesDailyPlanAgendaRequest;
use App\Models\Lead;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\Models\SalesDailyPlan;
use App\Models\User;
use App\Notifications\DailyPlanSubmittedNotification;
use App\Services\DailyAgendaPlanner;
use App\Services\SalesDailyPlanService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDailyPlanController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private SalesDailyPlanService $dailyPlans,
        private DailyAgendaPlanner $planner,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $date = $this->date($request->input('date'));
        $team = $this->dailyPlans->resolveSalesTeam($user);
        $coordinatorTeam = $team === null ? $this->dailyPlans->resolveCoordinatorTeam($user) : null;

        if ($coordinatorTeam !== null) {
            return view('sales-daily-plans.team-index', [
                'team' => $coordinatorTeam,
                'date' => $date,
                'plans' => $this->dailyPlans->plansForCoordinator($user, $date),
                'requiredMinutes' => SalesDailyPlan::REQUIRED_WORK_MINUTES,
                'planner' => $this->planner,
            ]);
        }

        $plan = $team === null ? null : $this->dailyPlans->forSales($user, $date, $team);

        return $this->render($plan, $user);
    }

    public function show(SalesDailyPlan $salesDailyPlan): View
    {
        $this->authorize('view', $salesDailyPlan);

        return $this->render($salesDailyPlan->load(['sales', 'team', 'agendas.lead', 'agendas.salesCase.consumer']), request()->user());
    }

    public function storeAgenda(StoreSalesDailyPlanAgendaRequest $request, SalesDailyPlan $salesDailyPlan): RedirectResponse
    {
        $this->authorize('update', $salesDailyPlan);
        $data = $request->validated();
        $salesDailyPlan->load('team');

        if ($salesDailyPlan->status === 'submitted' && ! $this->dailyPlans->isCoordinatorForPlan($request->user(), $salesDailyPlan)) {
            abort(403);
        }

        $this->ensureLinksBelongToPlan($salesDailyPlan, $data);

        DB::transaction(function () use ($data, $request, $salesDailyPlan): void {
            $plan = SalesDailyPlan::query()->lockForUpdate()->with('team')->findOrFail($salesDailyPlan->getKey());
            $agenda = SalesAgenda::query()->create([
                ...$data,
                'branch_id' => $plan->team->branch_id,
                'team_id' => $plan->team_id,
                'daily_plan_id' => $plan->getKey(),
                'created_by' => $request->user()->getKey(),
                'owner_id' => $plan->sales_id,
                'agenda_date' => $plan->plan_date->toDateString(),
                'status' => 'planned',
            ]);

            $plan->updateQuietly(['last_modified_by' => $request->user()->getKey()]);
            $this->dailyPlans->recalculateAndValidate($plan, $plan->status === 'submitted');
        });

        return redirect()->route('daily-plans.show', $salesDailyPlan)->with('status', 'agenda-created');
    }

    public function reorder(Request $request, SalesDailyPlan $salesDailyPlan): RedirectResponse
    {
        $this->authorize('update', $salesDailyPlan);
        $data = $request->validate(['agenda_ids' => ['required', 'array', 'min:1'], 'agenda_ids.*' => ['integer']]);
        $this->dailyPlans->reorder($salesDailyPlan, array_map('intval', $data['agenda_ids']), $request->user());

        return back()->with('status', 'agenda-reordered');
    }

    public function submit(Request $request, SalesDailyPlan $salesDailyPlan): RedirectResponse
    {
        $this->authorize('submit', $salesDailyPlan);
        ['plan' => $plan, 'coordinator' => $coordinator] = $this->dailyPlans->submit($salesDailyPlan, $request->user());
        $coordinator?->notify(new DailyPlanSubmittedNotification($plan));

        return redirect()->route('daily-plans.show', $plan)->with('status', 'daily-plan-submitted');
    }

    private function render(?SalesDailyPlan $plan, User $user): View
    {
        $plan?->load(['sales', 'team', 'agendas.lead', 'agendas.salesCase.consumer']);
        $total = $plan === null ? 0 : $this->planner->effectiveMinutes($plan);

        return view('sales-daily-plans.show', [
            'plan' => $plan,
            'totalMinutes' => $total,
            'requiredMinutes' => SalesDailyPlan::REQUIRED_WORK_MINUTES,
            'canEdit' => $plan !== null && $this->dailyPlans->canEdit($user, $plan),
            'isCoordinator' => $plan !== null && $this->dailyPlans->isCoordinatorForPlan($user, $plan),
            'categories' => SalesAgenda::CATEGORIES,
            'leads' => $plan === null ? collect() : $this->availableLeads($plan),
            'salesCases' => $plan === null ? collect() : $this->availableSalesCases($plan),
        ]);
    }

    private function date(?string $date): string
    {
        try {
            return CarbonImmutable::parse($date ?: BusinessTime::now()->toDateString(), config('app.business_timezone', 'Asia/Jakarta'))->toDateString();
        } catch (\Throwable) {
            return BusinessTime::now()->toDateString();
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureLinksBelongToPlan(SalesDailyPlan $plan, array $data): void
    {
        if (! empty($data['lead_id']) && ! Lead::query()->whereKey($data['lead_id'])->where('team_id', $plan->team_id)->exists()) {
            throw ValidationException::withMessages(['lead_id' => 'Lead harus berada di Team rencana kerja ini.']);
        }

        if (! empty($data['sales_case_id']) && ! SalesCase::query()->whereKey($data['sales_case_id'])->whereHas('lot.project', fn ($query) => $query->where('branch_id', $plan->team->branch_id))->exists()) {
            throw ValidationException::withMessages(['sales_case_id' => 'Sales Case harus berada di cabang Team ini.']);
        }
    }

    private function availableLeads(SalesDailyPlan $plan): Collection
    {
        return Lead::query()->where('team_id', $plan->team_id)->with('salesPic')->latest('tanggal_lead')->limit(100)->get();
    }

    private function availableSalesCases(SalesDailyPlan $plan): Collection
    {
        return SalesCase::query()->active()->whereHas('lot.project', fn ($query) => $query->where('branch_id', $plan->team->branch_id))->with(['consumer', 'lot.project'])->latest()->limit(100)->get();
    }
}
