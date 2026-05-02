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
        'status_terjual',
        'latitude',
        'longitude',
        'is_promoted',
        'promoted_until',
    ];

    protected $casts = [
        'is_promoted'    => 'boolean',
        'status_terjual' => 'boolean',
        'promoted_until' => 'datetime',
        'harga'          => 'integer',
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
}
