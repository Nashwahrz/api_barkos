<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
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

        foreach ($products->random(min(6, $products->count())) as $product) {
            $buyer = $buyers->random();

            if ($buyer->id === $product->id_pengguna) {
                continue;
            }

            $firstMessage = Chat::factory()->create([
                'id_pengirim' => $buyer->id,
                'id_penerima' => $product->id_pengguna,
                'id_produk' => $product->id_produk,
                'pesan' => 'Halo, barang ini masih tersedia?',
            ]);

            Chat::factory()->create([
                'id_pengirim' => $product->id_pengguna,
                'id_penerima' => $buyer->id,
                'id_produk' => $product->id_produk,
                'pesan' => 'Masih tersedia kak, silakan diorder.',
                'id_balasan' => $firstMessage->id_obrolan,
            ]);
        }
    }
}
