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
            'foto' => 'https://via.placeholder.com/640x480.png',
            'kondisi' => fake()->randomElement(['baru', 'sangat baik', 'layak pakai']),
            'status_terjual' => false,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
