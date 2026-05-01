<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'product_id',
        'seller_id',
        'package_id',
        'start_at',
        'end_at',
        'amount_paid',
        'status',
    ];

    protected $casts = [
        'start_at'    => 'datetime',
        'end_at'      => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    /**
     * Get the promoted product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the seller who purchased this promotion.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Get the promotion package used.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PromotionPackage::class, 'package_id');
    }

    /**
     * Scope: only active promotions (status=active AND end_at > now).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('end_at', '>', now());
    }
}
