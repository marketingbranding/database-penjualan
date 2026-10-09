<?php

namespace Database\Factories;

use App\Models\Consumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumer>
 */
class ConsumerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no_ktp' => fake()->unique()->numerify('################'),
            'nama_konsumen' => fake()->name(),
            'tanggal_lahir' => fake()->dateTimeBetween('-65 years', '-18 years'),
            'pekerjaan' => fake()->randomElement(['Karyawan Swasta', 'Wiraswasta', 'PNS']),
            'detail_pekerjaan' => fake()->company(),
            'alamat' => fake()->streetAddress(),
            'kelurahan' => fake()->citySuffix(),
            'kecamatan' => fake()->city(),
            'kabupaten_kota' => fake()->city(),
            'no_hp' => fake()->numerify('08##########'),
            'nama_kontak_darurat' => fake()->name(),
            'no_hp_kontak_darurat' => fake()->numerify('08##########'),
        ];
    }
}
