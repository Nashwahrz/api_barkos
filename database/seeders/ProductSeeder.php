<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sellers = User::where('role', 'penjual')->get();
        $categories = Category::all();

        if ($sellers->isEmpty() || $categories->isEmpty()) {
            return;
        }

        foreach ($sellers as $seller) {
            Product::factory()
                ->count(fake()->numberBetween(2, 5))
                ->for($seller)
                ->create([
                    'category_id' => fn () => $categories->random()->id,
                ])
                ->each(function (Product $product) {
                    $imageCount = fake()->numberBetween(1, 4);

                    for ($i = 0; $i < $imageCount; $i++) {
                        ProductImage::factory()->create([
                            'product_id' => $product->id,
                            'utama' => $i === 0,
                        ]);
                    }
                });
        }
    }
}
