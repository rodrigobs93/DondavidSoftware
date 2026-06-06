<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supplier_invoice_id', 'description', 'sale_unit', 'quantity',
        'unit_price', 'line_total', 'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function getFormattedQuantityAttribute(): string
    {
        if ($this->sale_unit === 'KG') {
            return number_format((float) $this->quantity, 3, '.', '') . ' kg';
        }
        return (int) $this->quantity . ' und';
    }
}
