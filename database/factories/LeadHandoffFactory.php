<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadHandoff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadHandoff>
 */
class LeadHandoffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'requested_by' => User::factory(),
            'reviewed_by' => null,
            'status' => 'submitted',
            'note' => 'Lead siap ditinjau oleh tim proses.',
            'review_note' => null,
            'reviewed_at' => null,
        ];
    }
}
