<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_otp',
        'otp_expires_at',
        'last_otp_sent_at',
         'reset_password_otp',
    'reset_password_expires_at',
        'is_verified',
        'email_verified_at',
        'reset_password_verified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'password' => 'hashed',
        'otp_expires_at' => 'datetime',
    'last_otp_sent_at' => 'datetime',

    ];


}