<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id', 'method', 'amount', 'paid_at', 'notes', 'registered_by_user_id',
        'verified', 'verified_at', 'verified_by_user_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public static array $methods = [
        'CASH'      => 'Efectivo',
        'CARD'      => 'Tarjeta',
        'NEQUI'     => 'Nequi',
        'DAVIPLATA' => 'Daviplata',
        'BREB'      => 'Bre-B',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::$methods[$this->method] ?? $this->method;
    }
}
