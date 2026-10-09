<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_lead' => '260627-ON-TI-01',
            'id_promo' => '260101-D1-01',
            'nama_promo' => 'DP 10jt All In',
            'tanggal_lead' => '2026-06-27',
            'sumber_lead' => 'Online',
            'kanal_masuk' => 'TikTok',
            'aktivitas_lead' => 'Live',
            'nama_konsumen' => fake()->name(),
            'no_hp' => '08'.fake()->numerify('##########'),
            'project_id' => null,
            'branch_id' => Branch::factory(),
            'team_id' => null,
            'sales_pic_id' => User::factory(),
            'created_by' => User::factory(),
            'status_lead' => 'No Respon',
            'keterangan' => null,
        ];
    }
}
