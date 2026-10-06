<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Support\Rbac;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Rbac::provision();

        Team::query()->each(fn (Team $team) => Rbac::provisionTeam($team));
    }
}
