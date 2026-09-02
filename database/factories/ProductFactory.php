<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'nama_barang' => fake()->words(3, true),
            'deskripsi' => fake()->paragraph(),
            'harga' => fake()->numberBetween(10000, 10000000),
            'foto' => null,
            'kondisi' => fake()->randomElement(['baru', 'sangat baik', 'layak pakai']),
            'durasi_pemakaian' => fake()->optional()->randomElement(['< 6 bulan', '6-12 bulan', '1-2 tahun', '> 2 tahun']),
            'status_terjual' => false,
            'latitude' => fake()->latitude(-0.95, -0.85),
            'longitude' => fake()->longitude(100.30, 100.45),
            'harga_minimum_tawaran' => null,
            'tawaran_diaktifkan' => false,
            'dipromosikan' => false,
            'metode_pembayaran' => fake()->randomElement(['cod', 'bank_transfer', 'both']),
        ];
    }
}
