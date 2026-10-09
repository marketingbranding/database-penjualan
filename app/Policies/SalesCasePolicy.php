<?php

namespace App\Policies;

use App\Models\SalesCase;
use App\Models\User;

class SalesCasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('consumers.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalesCase $salesCase): bool
    {
        return $user->can('consumers.view') && $this->belongsToUsersBranch($user, $salesCase);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('consumers.create')
            && ($user->canAccessAllBranches() || $user->branch_id !== null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesCase $salesCase): bool
    {
        return ! $salesCase->isCancelled()
            && $user->can('consumers.update')
            && $this->belongsToUsersBranch($user, $salesCase);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesCase $salesCase): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SalesCase $salesCase): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SalesCase $salesCase): bool
    {
        return false;
    }

    private function belongsToUsersBranch(User $user, SalesCase $salesCase): bool
    {
        return $user->canAccessAllBranches()
            || $salesCase->lot->project->branch_id === $user->branch_id;
    }
}
