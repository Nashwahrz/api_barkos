<?php

namespace Database\Seeders;

use App\Models\PromotionPackage;
use Illuminate\Database\Seeder;

class PromotionPackageSeeder extends Seeder
{
    /**
     * Seed the default promotion packages.
     * Phase 1.5 — TRD §8.1
     */
    public function run(): void
    {
        $packages = [
            [
                'nama'              => 'Coba-Coba (10 Orang)',
                'durasi_hari'       => 1,
                'harga'             => 1000.00,
                'aktif'             => true,
            ],
            [
                'nama'              => '1 Hari Boost',
                'durasi_hari'       => 1,
                'harga'             => 5000.00,
                'aktif'             => true,
            ],
            [
                'nama'              => '3 Hari Boost',
                'durasi_hari'       => 3,
                'harga'             => 12000.00,
                'aktif'             => true,
            ],
            [
                'nama'              => '7 Hari Boost',
                'durasi_hari'       => 7,
                'harga'             => 25000.00,
                'aktif'             => true,
            ],
        ];

        foreach ($packages as $package) {
            PromotionPackage::firstOrCreate(
                ['nama' => $package['nama']],
                $package
            );
        }
    }
}
