<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_produk' => Product::factory(),
            'id_pembeli' => User::factory(),
            'id_penjual' => User::factory(),
            'metode_pembayaran' => fake()->randomElement(['cod', 'bank_transfer']),
            'status' => fake()->randomElement(['pending', 'confirmed', 'completed', 'cancelled']),
            'jalur_bukti_pembayaran' => null,
            'harga_disepakati' => fake()->numberBetween(10000, 10000000),
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
