<?php

namespace App\Support;

use App\Models\Role;
use App\Models\Team;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class Rbac
{
    /** @var array<string, list<string>> */
    public const GLOBAL_ROLES = [
        'super_admin' => [
            'dashboard.view', 'spatial-workspace.view',
            'consumers.view', 'consumers.create', 'consumers.update', 'consumers.cancel', 'consumers.export',
            'sales-process.view', 'sales-process.update', 'psjb-utj.view', 'psjb-utj.create', 'psjb-utj.update', 'bi-checking.view', 'bi-checking.create', 'bi-checking.update', 'filing.view', 'filing.create', 'filing.update', 'bank-process.view', 'bank-process.create', 'bank-process.update', 'ppjb.view', 'ppjb.create', 'ppjb.update', 'akad.view', 'akad.create', 'akad.update', 'bast.view', 'bast.create', 'bast.update', 'lead-time.view', 'lead-time.update', 'warranties.view', 'warranties.create', 'warranties.update', 'settings.view',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'projects.view', 'projects.create', 'projects.update', 'projects.delete',
            'lots.view', 'lots.update', 'audit-logs.view', 'banks.view', 'banks.create', 'banks.update', 'banks.delete',
            'promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.assign-roles',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'teams.view', 'teams.create', 'teams.update', 'teams.delete',
            'sales.view-own', 'sales.view-team', 'sales.view-all',
            'sales.create', 'sales.update', 'sales.delete',
            'sales-targets.view-all', 'sales-targets.manage',
            'leads.view-own', 'leads.view-team', 'leads.view-branch', 'leads.view-all',
            'leads.create', 'leads.update-own', 'leads.update-team', 'leads.delete', 'leads.monitor',
            'sales-fee-reports.view', 'sales-fee-reports.print', 'sales-fee-reports.export',
            'reports.view', 'reports.export', 'reports.team-performance',
        ],
        'bm' => [
            'dashboard.view', 'spatial-workspace.view',
            'consumers.view', 'consumers.export',
            'sales-process.view', 'psjb-utj.view', 'bi-checking.view', 'filing.view', 'bank-process.view', 'ppjb.view', 'akad.view', 'bast.view', 'warranties.view',
            'promotions.view', 'lots.view', 'projects.view', 'branches.view', 'banks.view',
            'sales.view-all',
            'leads.view-branch', 'leads.monitor',
            'sales-fee-reports.view', 'sales-fee-reports.print', 'sales-fee-reports.export',
            'reports.view', 'reports.export', 'reports.team-performance',
        ],
        'spv' => [
            'dashboard.view', 'spatial-workspace.view',
            'consumers.view', 'consumers.create', 'consumers.update', 'consumers.export',
            'sales-process.view', 'psjb-utj.view', 'bi-checking.view', 'filing.view', 'bank-process.view', 'ppjb.view', 'akad.view', 'bast.view',
            'promotions.view',
            'sales.view-team', 'sales.create', 'sales.update',
            'sales-targets.view-team',
            'leads.view-branch', 'leads.monitor',
            'sales-fee-reports.view', 'sales-fee-reports.print', 'sales-fee-reports.export',
            'reports.view', 'reports.team-performance',
        ],
        'sas' => [
            'dashboard.view', 'spatial-workspace.view',
            'consumers.view', 'consumers.create', 'consumers.update', 'consumers.export',
            'sales-process.view', 'psjb-utj.view', 'bi-checking.view', 'filing.view', 'bank-process.view', 'ppjb.view', 'akad.view', 'bast.view',
            'promotions.view',
            'sales.view-team', 'sales.create', 'sales.update',
            'leads.view-branch', 'leads.monitor',
            'sales-fee-reports.view', 'sales-fee-reports.print', 'sales-fee-reports.export', 'reports.team-performance',
        ],
        'pusat' => [
            'dashboard.view', 'spatial-workspace.view',
            'consumers.view', 'consumers.create', 'consumers.update', 'consumers.export',
            'sales-process.view', 'sales-process.update',
            'psjb-utj.view', 'psjb-utj.create', 'psjb-utj.update',
            'bi-checking.view', 'bi-checking.create', 'bi-checking.update',
            'filing.view', 'filing.create', 'filing.update',
            'bank-process.view', 'bank-process.create', 'bank-process.update',
            'ppjb.view', 'ppjb.create', 'ppjb.update',
            'akad.view', 'akad.create', 'akad.update',
            'bast.view', 'bast.create', 'bast.update',
            'lead-time.view', 'lead-time.update',
            'warranties.view', 'warranties.create', 'warranties.update',
            'branches.view', 'projects.view', 'lots.view', 'banks.view', 'promotions.view',
            'sales.view-all',
            'leads.view-all', 'leads.monitor',
            'sales-fee-reports.view', 'sales-fee-reports.print', 'sales-fee-reports.export',
            'reports.view', 'reports.export', 'reports.team-performance',
        ],
        'sap' => [
            'dashboard.view',
            'sales-process.view',
            'filing.view', 'filing.create', 'filing.update',
            'leads.view-branch', 'leads.monitor',
            'sales-fee-reports.view', 'sales-fee-reports.print', 'sales-fee-reports.export', 'reports.team-performance',
        ],
    ];

    /** @var array<string, list<string>> */
    public const TEAM_ROLES = [
        'sales' => [
            'dashboard.view', 'sales.view-own', 'sales.create', 'sales.update', 'sales.delete',
            'sales-targets.view-own',
            'leads.view-own', 'leads.create', 'leads.update-own',
        ],
        'koordinator_sales' => [
            'dashboard.view', 'sales.view-team', 'sales.create', 'sales.update',
            'sales-targets.view-team',
            'leads.view-team', 'leads.create', 'leads.update-team',
        ],
    ];

    public static function provision(?Team $team = null): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GLOBAL_ROLES as $name => $permissions) {
            $role = Role::query()->firstOrCreate([
                'team_id' => null,
                'name' => $name,
                'guard_name' => 'web',
            ]);

            self::syncPermissionsIfChanged($role, $permissions);
        }

        if ($team !== null) {
            self::provisionTeam($team);
        }
    }

    public static function provisionTeam(Team $team): void
    {
        foreach (self::TEAM_ROLES as $name => $permissions) {
            $role = Role::query()->firstOrCreate([
                'team_id' => $team->getKey(),
                'name' => $name,
                'guard_name' => 'web',
            ]);

            self::syncPermissionsIfChanged($role, $permissions);
        }
    }

    /** @param list<string> $permissions */
    private static function syncPermissionsIfChanged(Role $role, array $permissions): void
    {
        $currentPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        $desiredPermissions = collect($permissions)->sort()->values()->all();

        if ($currentPermissions === $desiredPermissions) {
            return;
        }

        $role->syncPermissions($permissions);
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        return collect(self::GLOBAL_ROLES)
            ->merge(self::TEAM_ROLES)
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
