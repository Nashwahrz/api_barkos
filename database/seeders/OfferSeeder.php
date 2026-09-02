<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyers = User::where('role', 'pembeli')->get();
        $products = Product::where('status_terjual', false)->get();

        if ($buyers->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($products->random(min(8, $products->count())) as $product) {
            $buyer = $buyers->random();

            if ($buyer->id === $product->id_pengguna) {
                continue;
            }

            Offer::factory()->create([
                'id_produk' => $product->id_produk,
                'id_pembeli' => $buyer->id,
                'id_penjual' => $product->id_pengguna,
                'harga_tawaran' => (int) round($product->harga * fake()->randomFloat(2, 0.6, 0.95)),
            ]);
        }
    }
}
