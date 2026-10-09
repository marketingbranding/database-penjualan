<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('leads.view-own')
            || $user->can('leads.view-team')
            || $user->can('leads.view-branch')
            || $user->can('leads.view-all')
            || $user->can('leads.monitor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lead $lead): bool
    {
        if (! $user->canAccessAllBranches() && $user->branch_id !== $lead->branch_id) {
            return false;
        }

        if ($user->can('leads.view-all') || $user->can('leads.view-branch') || $user->can('leads.monitor')) {
            return true;
        }

        if ($user->can('leads.view-team')) {
            return $user->teams()->whereKey($lead->team_id)->exists();
        }

        return $user->can('leads.view-own') && $lead->sales_pic_id === $user->getKey();
    }

    public function monitor(User $user): bool
    {
        return $user->can('leads.monitor')
            || $user->can('leads.view-team')
            || $user->can('leads.view-all');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('leads.create') && $user->branch_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lead $lead): bool
    {
        if (! $this->view($user, $lead)) {
            return false;
        }

        if ($user->can('leads.view-all')) {
            return true;
        }

        if ($user->can('leads.update-team') && $user->teams()->whereKey($lead->team_id)->exists()) {
            return true;
        }

        return $user->can('leads.update-own') && $lead->sales_pic_id === $user->getKey();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->can('leads.delete') && $this->view($user, $lead);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lead $lead): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lead $lead): bool
    {
        return false;
    }
}
