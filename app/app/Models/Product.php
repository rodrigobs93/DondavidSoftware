<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'sale_unit', 'base_price', 'active',
        'price_updated_at', 'price_updated_by_user_id',
    ];

    protected $casts = [
        'base_price'       => 'decimal:2',
        'active'           => 'boolean',
        'price_updated_at' => 'datetime',
    ];

    public function priceUpdatedBy()
    {
        return $this->belongsTo(User::class, 'price_updated_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function isKg(): bool
    {
        return $this->sale_unit === 'KG';
    }

    public function isUnit(): bool
    {
        return $this->sale_unit === 'UNIT';
    }
}
