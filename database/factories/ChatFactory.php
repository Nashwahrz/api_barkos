<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat>
 */
class ChatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_pengirim' => User::factory(),
            'id_penerima' => User::factory(),
            'id_produk' => Product::factory(),
            'pesan' => fake()->sentence(),
            'sudah_dibaca' => fake()->boolean(70),
        ];
    }
}
