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

            if ($reporter->id === $product->user_id) {
                continue;
            }

            Report::factory()->create([
                'reporter_id' => $reporter->id,
                'product_id' => $product->id,
            ]);
        }
    }
}
