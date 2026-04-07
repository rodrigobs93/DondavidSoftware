<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditMovement extends Model
{
    const UPDATED_AT = null; // immutable ledger — no updates ever

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'amount',
        'type',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
