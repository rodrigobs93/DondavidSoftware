<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'invoice_id', 'quick_sale_id', 'status', 'payload', 'attempts', 'error_message',
        'queued_at', 'printed_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'queued_at'  => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function quickSale()
    {
        return $this->belongsTo(QuickSale::class);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'QUEUED');
    }
}
