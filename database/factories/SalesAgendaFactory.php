<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SalesAgenda;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesAgenda>
 */
class SalesAgendaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'created_by' => User::factory(),
            'team_id' => null,
            'sales_case_id' => null,
            'lead_id' => null,
            'agenda_date' => today(),
            'activity_category' => SalesAgenda::CATEGORIES[0],
            'title' => fake()->sentence(3),
            'location' => fake()->optional()->address(),
            'activity_result' => null,
            'status' => 'planned',
            'completed_at' => null,
        ];
    }
}
