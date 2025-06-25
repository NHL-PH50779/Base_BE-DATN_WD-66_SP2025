<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_status_id',
        'payment_status_id',
        'total',
        'created_at',
    ];

    // Order status constants
    const STATUS_PENDING = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_SHIPPING = 3;
    const STATUS_DELIVERED = 4;
    const STATUS_COMPLETED = 5;
    const STATUS_CANCELLED = 6;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }
    public function items()
{
    return $this->hasMany(OrderItem::class);
}
}
