<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name', 
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'quantity',
        'used_count',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function isValid()
    {
        return $this->is_active && 
               $this->quantity > $this->used_count &&
               Carbon::now()->between($this->start_date, $this->end_date);
    }

    public function calculateDiscount($orderAmount)
    {
        if (!$this->isValid() || $orderAmount < $this->min_order_amount) {
            return 0;
        }

        if ($this->type === 'fixed') {
            return min($this->value, $orderAmount);
        } else { // percent
            $discount = ($orderAmount * $this->value) / 100;
            return $this->max_discount_amount ? min($discount, $this->max_discount_amount) : $discount;
        }
    }
}