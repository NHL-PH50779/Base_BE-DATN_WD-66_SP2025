<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletLog extends Model {
    protected $fillable = ['user_id', 'amount', 'type', 'reason'];
}

