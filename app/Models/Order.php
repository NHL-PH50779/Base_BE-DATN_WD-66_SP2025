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
        'name',
        'phone',
        'email',
        'address',
        'note',
        'payment_method',
        'payment_status',
        'status',
        'coupon_code',
        'coupon_discount',
        'is_vnpay',
        'cancel_requested',
        'cancel_reason',
        'cancelled_at',
        'vnpay_txn_ref',
        'vnpay_transaction_no',
        'vnpay_response_code',
        'paid_at',
        'created_at',
    ];

    protected $casts = [
        'is_vnpay' => 'boolean',
        'cancel_requested' => 'boolean',
        'cancelled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // Order status constants
    const STATUS_PENDING = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_SHIPPING = 3;
    const STATUS_DELIVERED = 4;
    const STATUS_COMPLETED = 5;
    const STATUS_CANCELLED = 6;
    
    // Payment status constants
    const PAYMENT_PENDING = 1;
    const PAYMENT_PAID = 2;
    const PAYMENT_FAILED = 3;

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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    
    // Helper methods
    public function isCompleted()
    {
        return $this->order_status_id == self::STATUS_COMPLETED;
    }
    
    public function isPaid()
    {
        return $this->payment_status_id == self::PAYMENT_PAID;
    }
    
    public function isCod()
    {
        return $this->payment_method === 'cod';
    }
    
    public function getStatusTextAttribute()
    {
        $statuses = [
            1 => 'Chờ xác nhận',
            2 => 'Đã xác nhận', 
            3 => 'Đang giao hàng',
            4 => 'Đã giao hàng',
            5 => 'Hoàn thành',
            6 => 'Đã hủy'
        ];
        
        return $statuses[$this->order_status_id] ?? 'Không xác định';
    }
    
    public function getPaymentStatusTextAttribute()
    {
        $statuses = [
            1 => 'Chưa thanh toán',
            2 => 'Đã thanh toán',
            3 => 'Thanh toán thất bại'
        ];
        
        return $statuses[$this->payment_status_id] ?? 'Không xác định';
    }
}
