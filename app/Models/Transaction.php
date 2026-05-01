<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'product_id',
        'buyer_id',
        'seller_id',
        'payment_method',
        'status',
        'payment_proof_path',
        'agreed_price',
        'notes',
    ];

    protected $casts = [
        'agreed_price' => 'decimal:2',
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
