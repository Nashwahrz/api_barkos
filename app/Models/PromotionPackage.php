<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionPackage extends Model
{
    protected $fillable = [
        'name',
        'duration_days',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all promotions purchased under this package.
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'package_id');
    }
}
