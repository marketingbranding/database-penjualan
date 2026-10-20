<?php

namespace Database\Factories;

use App\Models\SalesLeadTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesLeadTarget>
 */
class SalesLeadTargetFactory extends Factory
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
            'period_year' => 2026,
            'period_month' => 10,
            'lead_target' => 40,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
