<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'consecutive', 'consecutive_int', 'customer_id', 'created_by_user_id',
        'invoice_date', 'subtotal', 'delivery_fee', 'total', 'paid_amount',
        'balance', 'status', 'requires_fe', 'fe_status', 'fe_reference',
        'fe_issued_at', 'fe_issued_by_user_id', 'notes', 'voided',
        'voided_at', 'voided_by_user_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal'     => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total'        => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'balance'      => 'decimal:2',
        'requires_fe'  => 'boolean',
        'voided'       => 'boolean',
        'fe_issued_at' => 'datetime',
        'voided_at'    => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function feIssuedBy()
    {
        return $this->belongsTo(User::class, 'fe_issued_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    /**
     * Shared filter scope: text search (consecutive + customer name/business_name)
     * and date range. Used by InvoiceController, CarteraController, FePendingController.
     */
    public function scopeApplyFilters($query, string $q, string $startDate, string $endDate): void
    {
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('consecutive', 'ilike', "%{$q}%")
                    ->orWhereHas('customer', fn($cq) => $cq
                        ->withTrashed()
                        ->where(fn($q2) => $q2
                            ->where('name', 'ilike', "%{$q}%")
                            ->orWhere('business_name', 'ilike', "%{$q}%")
                        )
                    );
            });
        }

        if ($startDate && $endDate) {
            $query->whereDate('invoice_date', '>=', $startDate)
                  ->whereDate('invoice_date', '<=', $endDate);
        } elseif ($startDate) {
            $query->whereDate('invoice_date', $startDate);
        } elseif ($endDate) {
            $query->whereDate('invoice_date', $endDate);
        }
    }

    public function isPaid(): bool    { return $this->status === 'PAID'; }
    public function isPartial(): bool { return $this->status === 'PARTIAL'; }
    public function isPending(): bool { return $this->status === 'PENDING'; }

    public function getFeLabelAttribute(): string
    {
        return match($this->fe_status) {
            'NONE'    => 'FE: NO',
            'PENDING' => 'FE: PENDIENTE',
            'ISSUED'  => "FE: EMITIDA - {$this->fe_reference}",
            default   => 'FE: NO',
        };
    }
}
