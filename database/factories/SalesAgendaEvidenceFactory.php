<?php

namespace Database\Factories;

use App\Models\SalesAgenda;
use App\Models\SalesAgendaEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesAgendaEvidence>
 */
class SalesAgendaEvidenceFactory extends Factory
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
            'file_path' => 'sales-agendas/example.jpg',
            'original_name' => 'example.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ];
    }
}
