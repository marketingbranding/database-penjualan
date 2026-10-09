<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\BankUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankUnit> */
class BankUnitFactory extends Factory
{
    public function definition(): array
    {
        return ['bank_id' => Bank::factory(), 'name' => fake()->city(), 'is_active' => true];
    }
}
