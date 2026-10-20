<?php

namespace App\Policies;

use App\Models\SalesLeadTarget;
use App\Models\Team;
use App\Models\User;

class SalesLeadTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales-targets.view-own')
            || $user->can('sales-targets.view-team')
            || $user->can('sales-targets.view-all');
    }

    public function view(User $user, SalesLeadTarget $target): bool
    {
        if ($user->can('sales-targets.view-all')) {
            return true;
        }

        if ((int) $target->sales_id === (int) $user->getKey()) {
            return $user->can('sales-targets.view-own');
        }

        if (! $user->can('sales-targets.view-team')) {
            return false;
        }

        return Team::query()
            ->where('is_active', true)
            ->where('coordinator_id', $user->getKey())
            ->whereHas('users', fn ($query) => $query->whereKey($target->sales_id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('sales-targets.manage');
    }

    public function update(User $user, SalesLeadTarget $target): bool
    {
        return $user->can('sales-targets.manage');
    }

    public function delete(User $user, SalesLeadTarget $target): bool
    {
        return false;
    }
}
