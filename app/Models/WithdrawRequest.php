<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'bank_name', 'account_number', 'account_name', 'status'
    ];

    // ✅ Quan hệ tới bảng users
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
