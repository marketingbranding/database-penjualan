<?php

namespace App\Policies;

use App\Models\SalesAgenda;
use App\Models\User;

class SalesAgendaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view-own') || $user->can('sales.view-team') || $user->can('sales.view-all');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalesAgenda $salesAgenda): bool
    {
        if (! $this->sameBranch($user, $salesAgenda)) {
            return false;
        }

        if ($user->can('sales.view-all')) {
            return true;
        }

        if ($salesAgenda->created_by === $user->getKey()) {
            return $user->can('sales.view-own') || $user->can('sales.view-team');
        }

        return $user->can('sales.view-team')
            && $user->teams()->whereKey($salesAgenda->team_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('sales.create') && $user->branch_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesAgenda $salesAgenda): bool
    {
        return $this->view($user, $salesAgenda)
            && ($salesAgenda->created_by === $user->getKey() || $user->can('sales.view-all'))
            && $user->can('sales.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesAgenda $salesAgenda): bool
    {
        return $this->update($user, $salesAgenda) && $user->can('sales.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SalesAgenda $salesAgenda): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SalesAgenda $salesAgenda): bool
    {
        return false;
    }

    private function sameBranch(User $user, SalesAgenda $salesAgenda): bool
    {
        return $user->canAccessAllBranches() || $user->branch_id === $salesAgenda->branch_id;
    }
}
