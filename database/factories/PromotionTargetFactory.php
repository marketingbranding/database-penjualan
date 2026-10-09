<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionTarget>
 */
class PromotionTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'branch_id' => Branch::factory(),
            'project_id' => null,
            'project_block_id' => null,
        ];
    }
}
