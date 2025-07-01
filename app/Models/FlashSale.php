<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class FlashSale extends Model
{
    protected $fillable = [
        'name',
        'description',
        'start_time',
        'end_time',
        'is_active'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class)->where('is_active', true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        $now = Carbon::now();
        return $query->where('start_time', '<=', $now)
                    ->where('end_time', '>=', $now);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', Carbon::now());
    }

    // Attributes
    public function getIsCurrentAttribute(): bool
    {
        $now = Carbon::now();
        return $this->start_time <= $now && $this->end_time >= $now && $this->is_active;
    }

    public function getTimeRemainingAttribute(): int
    {
        if (!$this->is_current) {
            return 0;
        }
        return $this->end_time->diffInSeconds(Carbon::now());
    }

    public function getStatusAttribute(): string
    {
        $now = Carbon::now();
        
        if (!$this->is_active) {
            return 'inactive';
        }
        
        if ($now < $this->start_time) {
            return 'upcoming';
        }
        
        if ($now > $this->end_time) {
            return 'ended';
        }
        
        return 'active';
    }
}