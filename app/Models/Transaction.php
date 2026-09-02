<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transaksi';
    protected $fillable = [
        'product_id',
        'buyer_id',
        'seller_id',
        'metode_pembayaran',
        'status',
        'jalur_bukti_pembayaran',
        'harga_disepakati',
        'catatan',
    ];

    protected $casts = [
        'harga_disepakati' => 'decimal:2',
    ];

    /**
     * Get the product associated with this transaction.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the buyer user.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller user.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
