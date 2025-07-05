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
        'coupon_code',
        'coupon_discount',
        'created_at',
        'vnpay_txn_ref',
        'vnpay_response_code',
        'vnpay_transaction_no',
        'payment_status',
        'paid_at',
        'total_amount',
        'status',
        'transaction_id'
    ];

    // Order status constants
    const STATUS_PENDING = 1;      // Chờ xác nhận
    const STATUS_CONFIRMED = 2;    // Đã xác nhận (có thể hủy)
    const STATUS_PREPARING = 3;    // Đang chuẩn bị
    const STATUS_SHIPPING = 4;     // Đang giao
    const STATUS_DELIVERED = 5;    // Đã giao
    const STATUS_CANCELLED = 6;    // Đã hủy
    
    // Payment status constants
    const PAYMENT_PENDING = 1;     // Chưa thanh toán
    const PAYMENT_PAID = 2;        // Đã thanh toán
    const PAYMENT_REFUNDED = 3;    // Đã hoàn tiền

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }
    
    public function cancelRequests()
    {
        return $this->hasMany(CancelRequest::class);
    }
    
    // Kiểm tra có thể yêu cầu hủy không
    public function canRequestCancel()
    {
        return $this->order_status_id == 2 && 
               $this->payment_method === 'vnpay' && 
               !$this->cancelRequests()->where('status', 'pending')->exists();
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    
    // Helper methods
    public function isCompleted()
    {
        return $this->order_status_id == self::STATUS_DELIVERED;
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
            2 => 'Đã xác nhận (có thể hủy)', 
            3 => 'Đang chuẩn bị',
            4 => 'Đang giao hàng',
            5 => 'Đã giao hàng',
            6 => 'Đã hủy'
        ];
        
        return $statuses[$this->order_status_id] ?? 'Không xác định';
    }
    
    public function getPaymentStatusTextAttribute()
    {
        $statuses = [
            1 => 'Chưa thanh toán',
            2 => 'Đã thanh toán',
            3 => 'Đã hoàn tiền'
        ];
        
        return $statuses[$this->payment_status_id] ?? 'Không xác định';
    }
    
    // Kiểm tra có thể hủy đơn hàng không
    public function canCancel()
    {
        return in_array($this->order_status_id, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }
    
    // Kiểm tra đơn hàng VNPay đã thanh toán
    public function isVnpayPaid()
    {
        return $this->payment_method === 'vnpay' && $this->payment_status === 'paid';
    }
}
