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

    // Cộng tiền vào ví
    public function credit($amount, $description, $referenceType = null, $referenceId = null)
    {
        $balanceBefore = $this->balance;
        $this->balance += $amount;
        $this->save();

        WalletTransaction::create([
            'user_id' => $this->user_id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId
        ]);

        return $this;
    }

    // Trừ tiền khỏi ví
    public function debit($amount, $description, $referenceType = null, $referenceId = null)
    {
        if ($this->balance < $amount) {
            throw new \Exception('Số dư ví không đủ');
        }

        $balanceBefore = $this->balance;
        $this->balance -= $amount;
        $this->save();

        WalletTransaction::create([
            'user_id' => $this->user_id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId
        ]);

        return $this;
    }
}