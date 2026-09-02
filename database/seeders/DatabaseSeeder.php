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
        $this->call([
            CategorySeeder::class,
            PromotionPackageSeeder::class,
            PaymentBankAccountSeeder::class,
            PaymentSettingSeeder::class,
            TestimoniSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            BankAccountSeeder::class,
            OfferSeeder::class,
            ChatSeeder::class,
            TransactionSeeder::class,
            ReportSeeder::class,
            FavoriteSeeder::class,
            ClosedChatSeeder::class,
            PromotionSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'kostmartpadang@gmail.com'],
            [
                'nama' => 'Super Admin',
                'asal_kampus' => 'PNP',
                'role' => 'super_admin',
                'email_verified_at' => now(),
                'password' => 'YakinBisa123!',
            ]
        );

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        //     'asal_kampus' => 'Institut Teknologi Bandung',
        //     'role' => 'pembeli',
        // ]);
    }
}
