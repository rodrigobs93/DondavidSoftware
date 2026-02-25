<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProductPrice extends Model
{
    protected $fillable = ['customer_id', 'product_id', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
