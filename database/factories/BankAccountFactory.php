<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_pengguna' => User::factory(),
            'nama_bank' => fake()->randomElement(['BCA', 'BNI', 'BRI', 'Mandiri', 'BSI', 'CIMB Niaga']),
            'nomor_rekening' => fake()->numerify('##########'),
            'nama_pemilik_rekening' => fake()->name(),
        ];
    }
}
