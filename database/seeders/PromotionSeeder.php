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
        $package = PromotionPackage::where('nama', '7 Hari Boost')->first();
        if (!$package) {
            $package = PromotionPackage::first();
        }

        // Ensure we have a seller
        $seller = User::firstOrCreate(
            ['email' => 'penjual@example.com'],
            [
                'nama' => 'Penjual Dummy',
                'password' => bcrypt('password'), // password
                'role' => 'penjual',
                'asal_kampus' => 'Universitas Gadjah Mada'
            ]
        );

        // Ensure we have a category
        $category = Category::firstOrCreate(
            ['nama' => 'Elektronik']
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
                'dipromosikan' => true,
                'dipromosikan_hingga' => Carbon::now()->addDays($package->durasi_hari),
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
                'mulai_pada' => Carbon::now(),
                'berakhir_pada' => Carbon::now()->addDays($package->durasi_hari),
                'jumlah_dibayar' => $package->harga,
                'status' => 'active',
            ]
        );
    }
}
