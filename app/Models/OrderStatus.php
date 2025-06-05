<?php

// app/Models/OrderStatus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    public $timestamps = false;
    protected $fillable = ['name'];
}

