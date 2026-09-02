<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionPackage extends Model
{
    protected $table = 'paket_promosi';
    protected $primaryKey = 'id_paket_promosi';
    protected $fillable = [
        'nama',
        'durasi_hari',
        'jumlah_penerima_acak',
        'harga',
        'aktif',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    /**
     * Get all promotions purchased under this package.
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'id_paket_promosi', 'id_paket_promosi');
    }
}
