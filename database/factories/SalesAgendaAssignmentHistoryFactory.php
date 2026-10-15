<?php

namespace Database\Factories;

use App\Models\SalesAgenda;
use App\Models\SalesAgendaAssignmentHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesAgendaAssignmentHistory>
 */
class SalesAgendaAssignmentHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_agenda_id' => SalesAgenda::factory(),
            'action' => 'redirected',
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'by_user_id' => User::factory(),
            'reason' => 'Perubahan penugasan untuk kebutuhan operasional.',
        ];
    }
}
