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
        'terjual_pada',
        'latitude',
        'longitude',
        'harga_minimum_tawaran',
        'tawaran_diaktifkan',
        'dipromosikan',
        'dipromosikan_hingga',
        'metode_pembayaran',
    ];

    protected $casts = [
        'dipromosikan'       => 'boolean',
        'tawaran_diaktifkan' => 'boolean',
        'status_terjual' => 'boolean',
        'dipromosikan_hingga' => 'datetime',
        'terjual_pada'   => 'datetime',
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
    public function getDipromosikanAttribute($value)
    {
        return $value && (!$this->dipromosikan_hingga || $this->dipromosikan_hingga->isFuture());
    }

    /**
     * Whether this product's active promotion should be surfaced (boosted/bannered/badged)
     * to a given viewer. Untargeted promotions (no random-recipient cap) show to everyone;
     * targeted ones only to the accounts rolled into id_pengguna_target. $bypassTargeting lets
     * the product owner and admins always see the true global status regardless of targeting.
     */
    public function isPromotedFor(?int $viewerId, bool $bypassTargeting = false): bool
    {
        if (!$this->dipromosikan) {
            return false;
        }

        if ($bypassTargeting) {
            return true;
        }

        $promotion = $this->relationLoaded('promotions')
            ? $this->promotions->first()
            : $this->promotions()
                ->where('status', 'active')
                ->where('status_pembayaran', 'paid')
                ->where('berakhir_pada', '>', now())
                ->latest()
                ->first();

        if (!$promotion || empty($promotion->id_pengguna_target)) {
            return true;
        }

        return $viewerId && in_array($viewerId, $promotion->id_pengguna_target);
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
        if (!auth('sanctum')->check()) {
            return false;
        }

        // We check if the relation is loaded to avoid N+1 queries if we eager loaded it.
        if ($this->relationLoaded('favorites')) {
            return $this->favorites->contains('user_id', auth('sanctum')->id());
        }

        return $this->favorites()->where('user_id', auth('sanctum')->id())->exists();
    }
}
