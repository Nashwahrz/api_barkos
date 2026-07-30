<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'midtrans_enabled',
        'manual_transfer_enabled',
        'qris_image_path',
    ];

    protected $casts = [
        'midtrans_enabled' => 'boolean',
        'manual_transfer_enabled' => 'boolean',
    ];

    /**
     * The single settings row is always id=1.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'midtrans_enabled' => true,
            'manual_transfer_enabled' => false,
        ]);
    }
}
