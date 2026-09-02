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

            if ($buyer->id === $product->id_pengguna) {
                continue;
            }

            DB::table('obrolan_selesai')->insertOrIgnore([
                'id_produk' => $product->id_produk,
                'id_pembeli' => $buyer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
