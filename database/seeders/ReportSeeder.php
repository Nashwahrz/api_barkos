<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reporters = User::where('role', 'pembeli')->get();
        $products = Product::all();

        if ($reporters->isEmpty() || $products->isEmpty()) {
            return;
        }

        foreach ($products->random(min(3, $products->count())) as $product) {
            $reporter = $reporters->random();

            if ($reporter->id === $product->id_pengguna) {
                continue;
            }

            Report::factory()->create([
                'id_pelapor' => $reporter->id,
                'id_produk' => $product->id_produk,
            ]);
        }
    }
}
