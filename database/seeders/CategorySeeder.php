<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Elektronik & Gadget'],
            ['name' => 'Furniture & Perabotan'],
            ['name' => 'Kasur & Alat Tidur'],
            ['name' => 'Peralatan Mandi & Cuci'],
            ['name' => 'Alat Masak & Makan'],
            ['name' => 'Buku & Alat Tulis Kampus'],
            ['name' => 'Kendaraan & Aksesoris'],
            ['name' => 'Pakaian & Fashion'],
            ['name' => 'Lain-lain (Lainnya)'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']]);
        }
    }
}
