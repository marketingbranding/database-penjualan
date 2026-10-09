<?php

namespace Database\Factories;

use App\Models\Consumer;
use App\Models\Lot;
use App\Models\SalesCase;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesCase>
 */
class SalesCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumer_id' => Consumer::factory(),
            'lot_id' => Lot::factory(),
            'payment_method' => PaymentMethod::Cash,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
