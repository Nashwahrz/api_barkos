<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'asal_kampus' => 'Universitas Indonesia',
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'asal_kampus' => 'Institut Teknologi Bandung',
            'role' => 'pembeli',
        ]);
    }
}
