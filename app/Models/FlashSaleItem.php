<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    protected $fillable = [
        'flash_sale_id',
        'product_id',
        'original_price',
        'sale_price',
        'discount_percentage',
        'quantity_limit',
        'sold_quantity',
        'is_active'
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'quantity_limit' => 'integer',
        'sold_quantity' => 'integer',
        'is_active' => 'boolean'
    ];

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->whereRaw('sold_quantity < quantity_limit');
    }

    // Attributes
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity_limit - $this->sold_quantity);
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->remaining_quantity > 0 && $this->is_active;
    }

    public function getSoldPercentageAttribute(): float
    {
        if ($this->quantity_limit == 0) {
            return 0;
        }
        return round(($this->sold_quantity / $this->quantity_limit) * 100, 2);
    }

    // Methods
    public function incrementSoldQuantity(int $quantity = 1): bool
    {
        if ($this->sold_quantity + $quantity > $this->quantity_limit) {
            return false;
        }

        $this->increment('sold_quantity', $quantity);
        return true;
    }
    
    public function decrementSoldQuantity(int $quantity = 1): bool
    {
        if ($this->sold_quantity - $quantity < 0) {
            return false;
        }

        $this->decrement('sold_quantity', $quantity);
        return true;
    }
}