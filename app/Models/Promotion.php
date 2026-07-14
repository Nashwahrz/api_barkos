<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'order_id',
        'snap_token',
        'payment_status',
        'product_id',
        'seller_id',
        'package_id',
        'start_at',
        'end_at',
        'amount_paid',
        'status',
        'ad_type',
        'ad_media_url',
        'ad_title',
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
     * Scope: only active promotions (status=active AND end_at > now AND payment_status = paid).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('payment_status', 'paid')
                     ->where('end_at', '>', now());
    }
}
