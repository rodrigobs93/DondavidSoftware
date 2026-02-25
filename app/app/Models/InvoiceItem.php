<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_id', 'product_id', 'product_name_snapshot',
        'sale_unit_snapshot', 'quantity', 'unit_price', 'line_total', 'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getFormattedQuantityAttribute(): string
    {
        if ($this->sale_unit_snapshot === 'KG') {
            return number_format((float)$this->quantity, 3, '.', '') . ' kg';
        }
        return (int)$this->quantity . ' und';
    }
}
