<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'harga_tawaran' => fake()->numberBetween(5000, 8000000),
            'status' => fake()->randomElement(['pending', 'accepted', 'rejected', 'cancelled']),
        ];
    }
}
