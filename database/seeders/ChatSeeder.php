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

            if ($buyer->id === $product->user_id) {
                continue;
            }

            $firstMessage = Chat::factory()->create([
                'sender_id' => $buyer->id,
                'receiver_id' => $product->user_id,
                'product_id' => $product->id,
                'pesan' => 'Halo, barang ini masih tersedia?',
            ]);

            Chat::factory()->create([
                'sender_id' => $product->user_id,
                'receiver_id' => $buyer->id,
                'product_id' => $product->id,
                'pesan' => 'Masih tersedia kak, silakan diorder.',
                'id_balasan' => $firstMessage->id,
            ]);
        }
    }
}
