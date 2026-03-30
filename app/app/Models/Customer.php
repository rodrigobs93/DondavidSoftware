<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'business_name', 'doc_type', 'doc_number', 'phone', 'address',
        'email', 'is_generic', 'requires_fe', 'notes', 'active', 'credit_balance',
    ];

    protected $casts = [
        'is_generic'     => 'boolean',
        'requires_fe'    => 'boolean',
        'active'         => 'boolean',
        'credit_balance' => 'decimal:2',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function specialPrices(): HasMany
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /** Pending invoices ordered oldest-first (for FIFO payment allocation) */
    public function pendingInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
                    ->where('balance', '>', 0)
                    ->where('voided', false)
                    ->orderBy('invoice_date', 'asc')
                    ->orderBy('id', 'asc');
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
