<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'province',
        'district',
        'ward',
        'address',
        'birth_date',
        'gender',
        'role',
        'status'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relationships
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    // Auto create wallet when user created
    protected static function boot()
    {
        parent::boot();
        
        static::created(function ($user) {
            $user->wallet()->create(['balance' => 0]);
        });
    }
}