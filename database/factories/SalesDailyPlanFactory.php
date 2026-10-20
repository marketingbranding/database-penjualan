<?php

namespace Database\Factories;

use App\Models\SalesDailyPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesDailyPlan>
 */
class SalesDailyPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_id' => User::factory(),
            'team_id' => Team::factory(),
            'plan_date' => '2026-10-20',
            'status' => 'draft',
            'submitted_at' => null,
            'submitted_by' => null,
            'last_modified_by' => null,
        ];
    }
}
