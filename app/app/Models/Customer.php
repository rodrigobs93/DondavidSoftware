<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'business_name', 'doc_type', 'doc_number', 'phone', 'address',
        'email', 'is_generic', 'requires_fe', 'notes', 'active',
    ];

    protected $casts = [
        'is_generic'  => 'boolean',
        'requires_fe' => 'boolean',
        'active'      => 'boolean',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function specialPrices()
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    public function getDocLabelAttribute(): string
    {
        if (!$this->doc_type || !$this->doc_number) {
            return '';
        }
        return "{$this->doc_type} {$this->doc_number}";
    }

    public static function generic(): ?self
    {
        return static::where('is_generic', true)->first();
    }
}
