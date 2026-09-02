<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentBankAccount extends Model
{
    protected $table = 'rekening_bank_pembayaran';
    protected $fillable = [
        'nama_bank',
        'nomor_rekening',
        'nama_pemilik_rekening',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];
}
