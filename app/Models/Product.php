<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model

{
    use SoftDeletes;
    protected $dates = ['deleted_at']; // Không bắt buộc nếu dùng Laravel 8+

    protected $fillable = [ 'name', 'description', 'brand_id', 'category_id', 'thumbnail', 'is_active' ];

    public $timestamps = false; // Nếu bạn không sử dụng created_at, updated_at


   public function variants()
{
    return $this->hasMany(ProductVariant::class);
}
}
