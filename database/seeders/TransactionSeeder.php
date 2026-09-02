<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
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

        foreach ($products->random(min(5, $products->count())) as $product) {
            $buyer = $buyers->random();

            if ($buyer->id === $product->user_id) {
                continue;
            }

            Transaction::factory()->create([
                'product_id' => $product->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $product->user_id,
                'harga_disepakati' => $product->harga,
            ]);
        }
    }
}
