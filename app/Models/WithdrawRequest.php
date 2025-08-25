<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    protected $fillable = [
        'user_id', 
        'amount', 
        'bank_name', 
        'account_number', 
        'account_name', 
        'status',
        'admin_note',
        'processed_at',
        'processed_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime'
    ];

    // Quan hệ tới bảng users
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Admin xử lý
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    
    // Helper methods
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }
    
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }
    
    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }
    
    // Format bank info
    public function getBankInfoAttribute()
    {
        return $this->bank_name . ' - ' . $this->account_number . ' - ' . $this->account_name;
    }
}