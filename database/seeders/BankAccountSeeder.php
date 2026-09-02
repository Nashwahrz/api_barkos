<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::where('role', 'penjual')->get()->each(function (User $seller) {
            BankAccount::factory()->create(['id_pengguna' => $seller->id]);
        });
    }
}
