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

            if ($buyer->id === $product->id_pengguna) {
                continue;
            }

            Transaction::factory()->create([
                'id_produk' => $product->id_produk,
                'id_pembeli' => $buyer->id,
                'id_penjual' => $product->id_pengguna,
                'harga_disepakati' => $product->harga,
            ]);
        }
    }
}
