<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentBankAccount>
 */
class PaymentBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_bank' => fake()->randomElement(['BCA', 'BNI', 'BRI', 'Mandiri']),
            'nomor_rekening' => fake()->numerify('##########'),
            'nama_pemilik_rekening' => 'Lapak Kos Official',
            'aktif' => true,
        ];
    }
}
