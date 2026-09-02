<?php

namespace Database\Seeders;

use App\Models\PaymentBankAccount;
use Illuminate\Database\Seeder;

class PaymentBankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['nama_bank' => 'BCA', 'nomor_rekening' => '1234567890', 'nama_pemilik_rekening' => 'Lapak Kos Official', 'aktif' => true],
            ['nama_bank' => 'BNI', 'nomor_rekening' => '0987654321', 'nama_pemilik_rekening' => 'Lapak Kos Official', 'aktif' => true],
        ];

        foreach ($accounts as $account) {
            PaymentBankAccount::firstOrCreate(
                ['nomor_rekening' => $account['nomor_rekening']],
                $account
            );
        }
    }
}
