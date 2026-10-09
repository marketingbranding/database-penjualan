<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\BankUnit;
use App\Models\BiChecking;
use App\Models\SalesCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BiChecking> */
class BiCheckingFactory extends Factory
{
    public function definition(): array
    {
        $bank = Bank::factory()->create();

        return [
            'sales_case_id' => SalesCase::factory(),
            'bank_id' => $bank->id,
            'bank_unit_id' => BankUnit::factory()->for($bank)->create()->id,
            'checking_date' => today(),
            'slik_result' => 'OK',
            'notes' => null,
            'lead_time_days' => null,
            'lead_time_status' => 'NON-SLA',
            'bank_decision' => 'Lanjut',
        ];
    }
}
