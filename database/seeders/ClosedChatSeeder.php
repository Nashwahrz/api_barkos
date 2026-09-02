<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClosedChatSeeder extends Seeder
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

        foreach ($products->random(min(2, $products->count())) as $product) {
            $buyer = $buyers->random();

            if ($buyer->id === $product->user_id) {
                continue;
            }

            DB::table('obrolan_selesai')->insertOrIgnore([
                'product_id' => $product->id,
                'buyer_id' => $buyer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
