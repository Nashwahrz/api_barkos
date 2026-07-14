<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionPackage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure promotion packages exist
        $this->call(PromotionPackage::class === PromotionPackageSeeder::class ? [] : [PromotionPackageSeeder::class]);

        // Get a package
        $package = PromotionPackage::where('name', '7 Hari Boost')->first();
        if (!$package) {
            $package = PromotionPackage::first();
        }

        // Ensure we have a seller
        $seller = User::firstOrCreate(
            ['email' => 'penjual@example.com'],
            [
                'name' => 'Penjual Dummy',
                'password' => bcrypt('password'), // password
                'role' => 'penjual',
                'asal_kampus' => 'Universitas Gadjah Mada'
            ]
        );

        // Ensure we have a category
        $category = Category::firstOrCreate(
            ['name' => 'Elektronik']
        );

        // Ensure we have a product
        $product = Product::firstOrCreate(
            ['nama_barang' => 'Laptop Bekas Promosi'],
            [
                'user_id' => $seller->id,
                'category_id' => $category->id,
                'deskripsi' => 'Laptop bekas masih bagus',
                'harga' => 2500000,
                'kondisi' => 'sangat baik',
                'status_terjual' => false,
                'is_promoted' => true,
                'promoted_until' => Carbon::now()->addDays($package->duration_days),
            ]
        );

        // Create the promotion
        Promotion::firstOrCreate(
            [
                'product_id' => $product->id,
                'seller_id' => $seller->id,
                'package_id' => $package->id,
            ],
            [
                'start_at' => Carbon::now(),
                'end_at' => Carbon::now()->addDays($package->duration_days),
                'amount_paid' => $package->price,
                'status' => 'active',
            ]
        );
    }
}
