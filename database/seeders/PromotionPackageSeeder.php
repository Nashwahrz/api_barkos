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
                'name'          => '1 Hari Boost',
                'duration_days' => 1,
                'price'         => 5000.00,
                'is_active'     => true,
            ],
            [
                'name'          => '3 Hari Boost',
                'duration_days' => 3,
                'price'         => 12000.00,
                'is_active'     => true,
            ],
            [
                'name'          => '7 Hari Boost',
                'duration_days' => 7,
                'price'         => 25000.00,
                'is_active'     => true,
            ],
        ];

        foreach ($packages as $package) {
            PromotionPackage::firstOrCreate(
                ['name' => $package['name']],
                $package
            );
        }
    }
}
