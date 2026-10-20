<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SalesDailyPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDailyPlanService
{
    public function __construct(private DailyAgendaPlanner $planner) {}

    public function forSales(User $sales, string $date, ?Team $team = null): SalesDailyPlan
    {
        $team ??= $this->resolveSalesTeam($sales);

        if ($team === null) {
            throw ValidationException::withMessages([
                'team_id' => 'Sales belum memiliki satu Tim final yang dapat digunakan untuk rencana kerja.',
            ]);
        }

        return SalesDailyPlan::query()->firstOrCreate(
            ['sales_id' => $sales->getKey(), 'plan_date' => $date],
            ['team_id' => $team->getKey(), 'status' => 'draft'],
        );
    }

    public function resolveSalesTeam(User $sales, ?int $requestedTeamId = null): ?Team
    {
        $teams = $sales->teams()
            ->where('teams.is_active', true)
            ->get()
            ->filter(fn (Team $team): bool => $this->hasTeamRole($sales, $team, 'sales'))
            ->values();

        if ($requestedTeamId !== null) {
            return $teams->firstWhere('id', $requestedTeamId);
        }

        if ($teams->count() === 1) {
            return $teams->first();
        }

        $activeTeamId = app()->runningInConsole() ? null : (int) request()->session()->get('active_team_id');

        return $teams->firstWhere('id', $activeTeamId);
    }

    public function resolveCoordinatorTeam(User $coordinator): ?Team
    {
        $teams = $coordinator->teams()
            ->where('teams.is_active', true)
            ->where('teams.coordinator_id', $coordinator->getKey())
            ->get()
            ->filter(fn (Team $team): bool => $this->hasTeamRole($coordinator, $team, 'koordinator_sales'))
            ->values();

        if ($teams->isEmpty()) {
            return null;
        }

        $activeTeamId = app()->runningInConsole() ? null : (int) request()->session()->get('active_team_id');

        return $teams->firstWhere('id', $activeTeamId)
            ?? $teams->first(fn (Team $team): bool => (bool) $team->pivot?->is_default)
            ?? $teams->first();
    }

    /** @return Collection<int, SalesDailyPlan> */
    public function plansForCoordinator(User $coordinator, string $date): Collection
    {
        $team = $this->resolveCoordinatorTeam($coordinator);

        if ($team === null) {
            return new Collection;
        }

        return SalesDailyPlan::query()
            ->where('team_id', $team->getKey())
            ->whereDate('plan_date', $date)
            ->with(['sales', 'agendas'])
            ->orderBy('sales_id')
            ->get();
    }

    public function isCoordinatorForPlan(User $coordinator, SalesDailyPlan $plan): bool
    {
        $team = $plan->relationLoaded('team') ? $plan->team : $plan->team()->first();

        return $team !== null
            && (int) $team->coordinator_id === (int) $coordinator->getKey()
            && $coordinator->teams()->whereKey($team->getKey())->exists()
            && $this->hasTeamRole($coordinator, $team, 'koordinator_sales');
    }

    public function canEdit(User $actor, SalesDailyPlan $plan): bool
    {
        if ($plan->status === 'draft' && (int) $plan->sales_id === (int) $actor->getKey()) {
            return true;
        }

        return $plan->status === 'submitted' && $this->isCoordinatorForPlan($actor, $plan);
    }

    public function reorder(SalesDailyPlan $plan, array $agendaIds, User $actor): void
    {
        DB::transaction(function () use ($plan, $agendaIds, $actor): void {
            $lockedPlan = SalesDailyPlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if (! $this->canEdit($actor, $lockedPlan)) {
                abort(403);
            }

            $agendas = $lockedPlan->agendas()->whereIn('id', $agendaIds)->get()->keyBy('id');

            if ($agendas->count() !== count($agendaIds)) {
                throw ValidationException::withMessages(['agendas' => 'Urutan agenda tidak valid untuk rencana kerja ini.']);
            }

            foreach (array_values($agendaIds) as $index => $agendaId) {
                $agendas[$agendaId]->updateQuietly(['sort_order' => $index + 1]);
            }

            $lockedPlan->updateQuietly(['last_modified_by' => $actor->getKey()]);
            $this->recalculateLocked($lockedPlan);
        });
    }

    /** @return array{plan: SalesDailyPlan, coordinator: ?User} */
    public function submit(SalesDailyPlan $plan, User $sales): array
    {
        $coordinator = null;

        DB::transaction(function () use ($plan, $sales, &$coordinator): void {
            $lockedPlan = SalesDailyPlan::query()->lockForUpdate()->with('team')->findOrFail($plan->getKey());

            if ((int) $lockedPlan->sales_id !== (int) $sales->getKey()) {
                abort(403);
            }

            if ($lockedPlan->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Rencana kerja ini sudah dikirim.']);
            }

            $this->recalculateLocked($lockedPlan);
            $this->planner->validateSubmission($lockedPlan->fresh());
            $coordinator = $this->deterministicCoordinator($lockedPlan->team);

            if ($coordinator === null) {
                throw ValidationException::withMessages(['team_id' => 'Tim Sales belum memiliki Koordinator final yang valid.']);
            }

            $lockedPlan->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => $sales->getKey(),
                'last_modified_by' => $sales->getKey(),
            ]);
        });

        return ['plan' => $plan->fresh(['sales', 'team', 'agendas']), 'coordinator' => $coordinator];
    }

    public function recalculateAndValidate(SalesDailyPlan $plan, bool $submitted): int
    {
        return DB::transaction(function () use ($plan, $submitted): int {
            $lockedPlan = SalesDailyPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $total = $this->recalculateLocked($lockedPlan);

            if ($submitted) {
                $this->planner->validateSubmission($lockedPlan->fresh());
            } else {
                $this->planner->validateDraftTotal($lockedPlan->fresh());
            }

            return $total;
        });
    }

    public function totalMinutes(SalesDailyPlan $plan): int
    {
        return $this->planner->effectiveMinutes($plan);
    }

    public function deterministicCoordinator(Team $team): ?User
    {
        $coordinator = $team->coordinator;

        return $coordinator !== null && $team->is_active && $this->hasTeamRole($coordinator, $team, 'koordinator_sales')
            ? $coordinator
            : null;
    }

    private function recalculateLocked(SalesDailyPlan $plan): int
    {
        return $this->planner->recalculate($plan->fresh(['agendas']));
    }

    private function hasTeamRole(User $user, Team $team, string $roleName): bool
    {
        $roleId = Role::query()->where('team_id', $team->getKey())->where('name', $roleName)->value('id');

        return $roleId !== null && DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $roleId)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->exists();
    }
}
