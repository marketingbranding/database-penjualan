<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectBlock>
 */
class ProjectBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => fake()->unique()->randomLetter(),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
