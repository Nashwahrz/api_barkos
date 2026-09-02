<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $table = 'pengaturan_pembayaran';
    protected $fillable = [
        'midtrans_diaktifkan',
        'transfer_manual_diaktifkan',
        'jalur_gambar_qris',
    ];

    protected $casts = [
        'midtrans_diaktifkan' => 'boolean',
        'transfer_manual_diaktifkan' => 'boolean',
    ];

    /**
     * The single settings row is always id=1.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'midtrans_diaktifkan' => true,
            'transfer_manual_diaktifkan' => false,
        ]);
    }
}
