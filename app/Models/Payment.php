<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'vnp_txn_ref',
        'amount',
        'vnp_response_code',
        'vnp_transaction_no',
        'vnp_bank_code',
        'vnp_pay_date',
        'vnp_order_info',
        'status',
        'vnp_data'
    ];

    protected $casts = [
        'vnp_data' => 'array',
        'amount' => 'decimal:2'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}