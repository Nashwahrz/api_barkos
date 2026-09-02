<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyers = User::where('role', 'pembeli')->get();
        $products = Product::all();

        if ($buyers->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($buyers as $buyer) {
            $available = $products->reject(fn (Product $p) => $p->user_id === $buyer->id);

            if ($available->isEmpty()) {
                continue;
            }

            $picks = $available->random(min(3, $available->count()));

            foreach ($picks as $product) {
                Favorite::firstOrCreate([
                    'user_id' => $buyer->id,
                    'product_id' => $product->id,
                ]);
            }
        }
    }
}
