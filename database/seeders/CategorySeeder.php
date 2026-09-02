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
            ['nama' => 'Elektronik & Gadget'],
            ['nama' => 'Furniture & Perabotan'],
            ['nama' => 'Kasur & Alat Tidur'],
            ['nama' => 'Peralatan Mandi & Cuci'],
            ['nama' => 'Alat Masak & Makan'],
            ['nama' => 'Buku & Alat Tulis Kampus'],
            ['nama' => 'Kendaraan & Aksesoris'],
            ['nama' => 'Pakaian & Fashion'],
            ['nama' => 'Lain-lain (Lainnya)'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['nama' => $category['nama']]);
        }
    }
}
