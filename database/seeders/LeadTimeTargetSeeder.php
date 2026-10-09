<?php

namespace Database\Seeders;

use App\Models\LeadTimeTarget;
use Illuminate\Database\Seeder;

class LeadTimeTargetSeeder extends Seeder
{
    public function run(): void
    {
        $targets = [
            ['process' => 'psjb', 'target_description' => 'BI Checking / PSJB (paralel)', 'target_days' => null],
            ['process' => 'pemberkasan', 'target_description' => 'PSJB - Pemberkasan', 'target_days' => 5],
            ['process' => 'proses_bank', 'target_description' => 'Pemberkasan - Proses Bank', 'target_days' => 10],
            ['process' => 'ppjb_dev', 'target_description' => 'Proses Bank - PPJB Dev', 'target_days' => 10],
            ['process' => 'akad', 'target_description' => 'PPJB Dev - Akad', 'target_days' => 3],
            ['process' => 'bast', 'target_description' => 'Ready 100% + Akad - BAST', 'target_days' => 1],
        ];

        foreach ($targets as $target) {
            LeadTimeTarget::query()->updateOrCreate(
                ['process' => $target['process']],
                [...$target, 'is_active' => true],
            );
        }
    }
}
