<?php

namespace Database\Factories;

use App\LotStatus;
use App\Models\Lot;
use App\Models\ProjectBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_block_id' => ProjectBlock::factory(),
            'project_id' => fn (array $attributes) => ProjectBlock::query()->findOrFail($attributes['project_block_id'])->project_id,
            'number' => fake()->numberBetween(1, 99),
            'code' => fn (array $attributes) => ProjectBlock::query()->findOrFail($attributes['project_block_id'])->code
                .str_pad((string) $attributes['number'], 2, '0', STR_PAD_LEFT),
            'status' => LotStatus::Available,
        ];
    }
}
