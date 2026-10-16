<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadOwnershipService
{
    /** @return Collection<int, User> */
    public function allowedSalesUsers(User $actor): Collection
    {
        if ($this->isPersonalSales($actor)) {
            return new Collection([$actor]);
        }

        if ($this->isCoordinator($actor)) {
            $team = $this->activeCoordinatorTeam($actor);

            return $team === null ? new Collection : $this->salesUsersForTeams([$team->getKey()], $actor->getKey());
        }

        $teamQuery = Team::query()->where('is_active', true);

        if (! $actor->canAccessAllBranches()) {
            $teamQuery->where('branch_id', $actor->branch_id);
        }

        return $this->salesUsersForTeams($teamQuery->pluck('id')->all());
    }

    /** @return array{0: int, 1: int} */
    public function resolve(User $actor, int $salesPicId): array
    {
        $salesPic = User::query()->with('teams')->findOrFail($salesPicId);
        $allowedSalesIds = $this->allowedSalesUsers($actor)->modelKeys();

        if (! in_array($salesPic->getKey(), $allowedSalesIds, true)) {
            throw ValidationException::withMessages(['sales_pic_id' => 'Sales PIC tidak tersedia dalam cakupan Anda.']);
        }

        $candidateTeamIds = $this->candidateTeamIds($actor);
        $salesTeamIds = $this->salesTeamIds($salesPic, $candidateTeamIds);
        $sessionTeamId = (int) request()->session()->get('active_team_id');
        $teamId = in_array($sessionTeamId, $salesTeamIds, true) ? $sessionTeamId : ($salesTeamIds[0] ?? null);

        if ($teamId === null) {
            throw ValidationException::withMessages(['sales_pic_id' => 'Sales PIC harus berada di tim Sales yang aktif.']);
        }

        if (! $actor->canAccessAllBranches() && $salesPic->branch_id !== $actor->branch_id) {
            throw ValidationException::withMessages(['sales_pic_id' => 'Sales PIC harus berasal dari cabang Anda.']);
        }

        return [$salesPic->branch_id, $teamId];
    }

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

    private function isPersonalSales(User $user): bool
    {
        return $user->can('sales.view-own')
            && $user->can('leads.view-own')
            && ! $user->can('sales.view-team')
            && ! $user->can('sales.view-all')
            && ! $user->can('leads.view-team')
            && ! $user->can('leads.view-branch')
            && ! $user->can('leads.view-all');
    }

    private function activeCoordinatorTeam(User $actor): ?Team
    {
        $teams = $actor->teams()
            ->where('teams.is_active', true)
            ->where('teams.coordinator_id', $actor->getKey())
            ->orderBy('teams.name')
            ->get();
        $requestedTeamId = (int) request()->session()->get('active_team_id');

        return $teams->firstWhere('id', $requestedTeamId)
            ?? $teams->first(fn (Team $team): bool => (bool) $team->pivot?->is_default)
            ?? $teams->first();
    }

    /** @param array<int, int> $teamIds */
    private function salesUsersForTeams(array $teamIds, ?int $excludeUserId = null): Collection
    {
        if ($teamIds === []) {
            return new Collection;
        }

        $salesUserIds = DB::table(config('permission.table_names.model_has_roles').' as model_roles')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'model_roles.role_id')
            ->whereIn('roles.team_id', $teamIds)
            ->where('roles.name', 'sales')
            ->where('model_roles.model_type', (new User)->getMorphClass())
            ->when($excludeUserId, fn ($query, int $userId) => $query->where('model_roles.model_id', '<>', $userId))
            ->pluck('model_roles.model_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        return User::query()
            ->whereIn('id', $salesUserIds)
            ->whereNotNull('branch_id')
            ->with('teams')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, int> */
    private function candidateTeamIds(User $actor): array
    {
        if ($this->isCoordinator($actor)) {
            return ($team = $this->activeCoordinatorTeam($actor)) === null ? [] : [$team->getKey()];
        }

        return Team::query()
            ->where('is_active', true)
            ->when(! $actor->canAccessAllBranches(), fn ($query) => $query->where('branch_id', $actor->branch_id))
            ->pluck('id')
            ->all();
    }

    /** @param array<int, int> $candidateTeamIds */
    private function salesTeamIds(User $salesPic, array $candidateTeamIds): array
    {
        $roleIds = Role::query()
            ->whereIn('team_id', $candidateTeamIds)
            ->where('name', 'sales')
            ->pluck('id');

        return DB::table(config('permission.table_names.model_has_roles'))
            ->whereIn('role_id', $roleIds)
            ->where('model_type', (new User)->getMorphClass())
            ->where('model_id', $salesPic->getKey())
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', config('permission.table_names.model_has_roles').'.role_id')
            ->join('teams', 'teams.id', '=', 'roles.team_id')
            ->where('teams.is_active', true)
            ->orderBy('teams.name')
            ->pluck('roles.team_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
