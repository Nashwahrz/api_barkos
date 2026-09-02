<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_produk' => Product::factory(),
            'jalur_gambar' => 'products/' . Str::uuid() . '.jpg',
            'utama' => false,
        ];
    }
}
