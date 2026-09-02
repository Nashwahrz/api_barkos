<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $table = 'promosi';
    protected $fillable = [
        'order_id',
        'snap_token',
        'status_pembayaran',
        'product_id',
        'seller_id',
        'package_id',
        'mulai_pada',
        'berakhir_pada',
        'jumlah_dibayar',
        'status',
        'jenis_iklan',
        'url_media_iklan',
        'judul_iklan',
        'metode_pembayaran',
        'jalur_bukti_manual',
        'status_peninjauan_manual',
        'catatan_ocr',
        'id_pengguna_target',
    ];

    protected $casts = [
        'mulai_pada'         => 'datetime',
        'berakhir_pada'      => 'datetime',
        'jumlah_dibayar'     => 'decimal:2',
        'id_pengguna_target' => 'array',
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
     * Scope: only active promotions (status=active AND berakhir_pada > now AND status_pembayaran = paid).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('status_pembayaran', 'paid')
                     ->where('berakhir_pada', '>', now());
    }

    /**
     * Scope: promotions visible to a given viewer — untargeted promotions (no
     * random-recipient cap) are visible to everyone; targeted ones only to the
     * user IDs rolled into id_pengguna_target.
     */
    public function scopeVisibleTo($query, ?int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('id_pengguna_target');
            if ($userId) {
                $q->orWhereJsonContains('id_pengguna_target', $userId);
            }
        });
    }
}
