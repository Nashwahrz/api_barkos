<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'rekening_bank';
    protected $fillable = [
        'user_id',
        'nama_bank',
        'nomor_rekening',
        'nama_pemilik_rekening',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
