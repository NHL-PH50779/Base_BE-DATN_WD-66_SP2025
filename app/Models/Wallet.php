<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance'
    ];

    protected $casts = [
        'balance' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'user_id', 'user_id');
    }

    public function addMoney($amount, $description = 'Nạp tiền', $referenceType = null, $referenceId = null)
    {
        return \DB::transaction(function () use ($amount, $description, $referenceType, $referenceId) {
            $this->lockForUpdate();
            $balanceBefore = $this->balance;
            $this->balance += $amount;
            $this->save();

            return WalletTransaction::create([
                'user_id' => $this->user_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId
            ]);
        });
    }

    public function subtractMoney($amount, $description = 'Thanh toán', $referenceType = null, $referenceId = null)
    {
        return \DB::transaction(function () use ($amount, $description, $referenceType, $referenceId) {
            $this->lockForUpdate();
            
            if ($this->balance < $amount) {
                throw new \Exception('Số dư không đủ');
            }

            $balanceBefore = $this->balance;
            $this->balance -= $amount;
            $this->save();

            return WalletTransaction::create([
                'user_id' => $this->user_id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId
            ]);
        });
    }
}