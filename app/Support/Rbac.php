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
            'dashboard.view',
            'consumers.view', 'sales-process.view', 'warranties.view', 'settings.view',
            'branches.view', 'projects.view', 'lots.view', 'banks.view', 'promotions.view',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.assign-roles',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'teams.view', 'teams.create', 'teams.update', 'teams.delete',
            'sales.view-own', 'sales.view-team', 'sales.view-all',
            'sales.create', 'sales.update', 'sales.delete', 'sales.approve',
            'reports.view', 'reports.export',
        ],
        'admin' => [
            'dashboard.view',
            'consumers.view', 'sales-process.view', 'warranties.view', 'settings.view',
            'branches.view', 'projects.view', 'lots.view', 'banks.view', 'promotions.view',
            'users.view', 'users.create', 'users.update', 'users.assign-roles',
            'teams.view', 'teams.create', 'teams.update',
            'sales.view-all', 'sales.create', 'sales.update', 'sales.delete', 'sales.approve',
            'reports.view', 'reports.export',
        ],
        'direksi' => [
            'dashboard.view', 'sales-process.view', 'warranties.view',
            'sales.view-all', 'reports.view', 'reports.export',
        ],
    ];

    /** @var array<string, list<string>> */
    public const TEAM_ROLES = [
        'sales' => [
            'dashboard.view', 'sales.view-own', 'sales.create', 'sales.update',
        ],
        'supervisor' => [
            'dashboard.view', 'sales-process.view', 'sales.view-team', 'sales.create', 'sales.update',
            'sales.approve', 'reports.view',
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

            $role->syncPermissions($permissions);
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

            $role->syncPermissions($permissions);
        }
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
