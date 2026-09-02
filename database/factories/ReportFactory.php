<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_pelapor' => User::factory(),
            'id_produk' => Product::factory(),
            'alasan' => fake()->randomElement([
                'Barang tidak sesuai deskripsi',
                'Penjual tidak responsif',
                'Diduga penipuan',
                'Harga tidak wajar',
                'Konten tidak pantas',
            ]),
            'deskripsi' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['pending', 'investigated', 'resolved', 'dismissed']),
        ];
    }
}
