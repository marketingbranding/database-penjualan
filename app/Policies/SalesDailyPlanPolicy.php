<?php

namespace App\Policies;

use App\Models\SalesDailyPlan;
use App\Models\User;
use App\Services\SalesDailyPlanService;

class SalesDailyPlanPolicy
{
    public function __construct(private SalesDailyPlanService $dailyPlans) {}

    public function view(User $user, SalesDailyPlan $plan): bool
    {
        if ($user->can('sales.view-all')) {
            return true;
        }

        if ((int) $plan->sales_id === (int) $user->getKey()) {
            return $user->can('sales.view-own');
        }

        return $user->can('sales.view-team') && $this->dailyPlans->isCoordinatorForPlan($user, $plan);
    }

    public function update(User $user, SalesDailyPlan $plan): bool
    {
        return $this->view($user, $plan) && $this->dailyPlans->canEdit($user, $plan);
    }

    public function submit(User $user, SalesDailyPlan $plan): bool
    {
        return $plan->status === 'draft'
            && (int) $plan->sales_id === (int) $user->getKey()
            && $user->can('sales.update');
    }
}
