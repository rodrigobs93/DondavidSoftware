<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'tax_id', 'phone', 'contact', 'notes', 'active', 'credit_balance',
    ];

    protected $casts = [
        'active'         => 'boolean',
        'credit_balance' => 'decimal:2',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** Pending invoices ordered oldest-first (for FIFO payment allocation) */
    public function pendingInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class)
                    ->where('balance', '>', 0)
                    ->where('voided', false)
                    ->orderBy('invoice_date', 'asc')
                    ->orderBy('id', 'asc');
    }
}
