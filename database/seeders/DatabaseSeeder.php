<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $team = Team::query()->firstOrCreate(
            ['code' => 'GENERAL'],
            ['name' => 'General', 'is_active' => true],
        );

        Rbac::provisionTeam($team);

        $email = config('rbac.super_admin.email');
        $password = config('rbac.super_admin.password');

        if (! $email || ! $password) {
            return;
        }

        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => config('rbac.super_admin.name') ?: 'Super Admin',
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $user->teams()->syncWithoutDetaching([
            $team->getKey() => ['is_default' => true],
        ]);

        $superAdminRole = Role::query()
            ->whereNull('team_id')
            ->where('name', 'super_admin')
            ->firstOrFail();

        $user->globalRoles()->syncWithoutDetaching([$superAdminRole->getKey()]);
    }
}
