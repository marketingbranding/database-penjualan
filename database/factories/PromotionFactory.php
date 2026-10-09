<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promo_id' => 'PROMO-'.fake()->date('Ym').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => fake()->sentence(3),
            'start_date' => today()->subDays(10)->toDateString(),
            'end_date' => today()->addDays(20)->toDateString(),
            'description' => null,
        ];
    }
}
