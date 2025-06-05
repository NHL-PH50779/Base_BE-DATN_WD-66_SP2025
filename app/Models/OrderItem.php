<?php

// app/Models/OrderItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id', 'variant_id', 'quantity', 'price'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
