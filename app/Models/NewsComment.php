<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsComment extends Model
{
     public $timestamps = false;
    protected $fillable = ['user_id', 'news_id', 'content'];
}
