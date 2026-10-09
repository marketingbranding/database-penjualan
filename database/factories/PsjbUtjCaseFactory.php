<?php

namespace Database\Factories;

use App\Models\PsjbUtjCase;
use App\Models\SalesCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PsjbUtjCase>
 */
class PsjbUtjCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sales_case_id' => SalesCase::factory(),
            'psjb_id' => fake()->unique()->bothify('######-PSJB-####'),
            'psjb_date' => today(),
            'unit_price' => 166000000,
            'utj_date' => today(),
            'utj_amount' => 500000,
            'dp_all_in' => 15000000,
            'dp_installment_amount' => 5000000,
            'dp_installment_count' => 3,
            'land_area_m2' => 0,
            'land_price_per_m2' => 1250000,
            'land_total' => 0,
            'consumer_status' => 'LANJUT',
            'filing_status' => 'INTERNAL',
        ];
    }
}
