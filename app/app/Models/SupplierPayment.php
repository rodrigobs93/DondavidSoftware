<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id', 'supplier_invoice_id', 'amount', 'method',
        'paid_at', 'notes', 'registered_by_user_id', 'submission_key',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /** Payment methods used when paying suppliers. */
    public static array $methods = [
        'CASH'      => 'Efectivo',
        'NEQUI'     => 'Nequi',
        'DAVIPLATA' => 'Daviplata',
        'DAVIVIENDA' => 'Davivienda',
        'OTHER'     => 'Otras cuentas',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    /** FIFO distribution rows generated from this payment. */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::$methods[$this->method] ?? $this->method;
    }
}
