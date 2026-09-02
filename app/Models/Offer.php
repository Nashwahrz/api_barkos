<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $table = 'tawaran';
    protected $primaryKey = 'id_tawaran';
    protected $fillable = [
        'id_produk',
        'id_pembeli',
        'id_penjual',
        'harga_tawaran',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_produk', 'id_produk');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'id_pembeli', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'id_penjual', 'id');
    }
}
