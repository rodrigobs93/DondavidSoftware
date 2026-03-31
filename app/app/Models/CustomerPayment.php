<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    protected $fillable = [
        'customer_id',
        'amount',
        'method',
        'paid_at',
        'notes',
        'registered_by_user_id',
        'verified',
        'verified_at',
        'verified_by_user_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
    ];

    /** Same methods as Payment */
    public static array $methods = [
        'CASH'      => 'Efectivo',
        'CARD'      => 'Tarjeta',
        'NEQUI'     => 'Nequi',
        'DAVIPLATA' => 'Daviplata',
        'BREB'      => 'Bre-B',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** Individual payment allocations generated from this customer payment */
    public function allocations(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_payment_id');
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
