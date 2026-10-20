<?php

namespace App\Providers;

use App\Models\BiChecking;
use App\Models\Consumer;
use App\Models\Lead;
use App\Models\LeadTimeTarget;
use App\Models\Lot;
use App\Models\Promotion;
use App\Models\PsjbUtjCase;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\Models\SalesCaseChangeRequest;
use App\Models\SalesCaseStatusHistory;
use App\Models\SalesLeadTarget;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Observers\LotObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Consumer::observe(AuditableObserver::class);
        SalesCase::observe(AuditableObserver::class);
        Lot::observe([AuditableObserver::class, LotObserver::class]);
        User::observe(AuditableObserver::class);
        Role::observe(AuditableObserver::class);
        Promotion::observe(AuditableObserver::class);
        PsjbUtjCase::observe(AuditableObserver::class);
        SalesCaseChangeRequest::observe(AuditableObserver::class);
        BiChecking::observe(AuditableObserver::class);
        SalesCaseStatusHistory::observe(AuditableObserver::class);
        SalesAgenda::observe(AuditableObserver::class);
        Lead::observe(AuditableObserver::class);
        LeadTimeTarget::observe(AuditableObserver::class);
        SalesLeadTarget::observe(AuditableObserver::class);

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasGlobalRole('super_admin')) {
                return true;
            }

            return $user->hasGlobalPermission($ability) ? true : null;
        });
    }
}
