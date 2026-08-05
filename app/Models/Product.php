<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'category_id',
        'nama_barang',
        'deskripsi',
        'harga',
        'foto',
        'kondisi',
        'durasi_pemakaian',
        'status_terjual',
        'latitude',
        'longitude',
        'minimum_offer_price',
        'is_offer_enabled',
        'is_promoted',
        'promoted_until',
    ];

    protected $casts = [
        'is_promoted'      => 'boolean',
        'is_offer_enabled' => 'boolean',
        'status_terjual' => 'boolean',
        'promoted_until' => 'datetime',
        'harga'          => 'integer',
    ];

    protected $appends = [
        'is_favorited',
    ];

    /**
     * Get the user that owns the product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category that the product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the chats for the product.
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * Get the images for this product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Get the transactions for this product.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the promotions for this product.
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /**
     * Dynamically determine if the product is currently promoted.
     */
    public function getIsPromotedAttribute($value)
    {
        return $value && (!$this->promoted_until || $this->promoted_until->isFuture());
    }

    /**
     * Get the favorites for this product.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Check if the product is favorited by the currently authenticated user.
     */
    public function getIsFavoritedAttribute()
    {
        if (!auth()->check()) {
            return false;
        }

        // We check if the relation is loaded to avoid N+1 queries if we eager loaded it.
        if ($this->relationLoaded('favorites')) {
            return $this->favorites->contains('user_id', auth()->id());
        }

        return $this->favorites()->where('user_id', auth()->id())->exists();
    }
}
