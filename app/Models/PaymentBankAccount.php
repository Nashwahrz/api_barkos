<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentBankAccount extends Model
{
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
