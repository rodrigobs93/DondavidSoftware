<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickSale extends Model
{
    protected $fillable = [
        'receipt_number', 'receipt_int', 'sale_date', 'total_amount',
        'payment_method', 'cash_received', 'change_amount',
        'notes', 'created_by_user_id', 'submission_key',
    ];

    protected $casts = [
        'sale_date'     => 'date',
        'total_amount'  => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
