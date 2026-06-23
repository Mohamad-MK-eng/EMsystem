<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'amount',
        'status',
        'transaction_ref',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
