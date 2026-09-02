<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Testimoni>
 */
class TestimoniFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'pekerjaan' => fake()->randomElement(['Mahasiswa', 'Freelancer', 'Karyawan Swasta', 'Wiraswasta']),
            'jenis_kelamin' => fake()->randomElement(['laki-laki', 'perempuan']),
            'tanggal_lahir' => fake()->dateTimeBetween('-27 years', '-18 years'),
        ];
    }
}
