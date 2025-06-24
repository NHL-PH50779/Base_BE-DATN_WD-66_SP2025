<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at']; // Không bắt buộc nếu dùng Laravel 8+

    protected $fillable = [ 'name', 'description', 'brand_id', 'category_id', 'thumbnail','cloudinary_public_id', 'is_active' ];

    public $timestamps = true;


   public function variants()
{
    return $this->hasMany(ProductVariant::class);
}

public function brand()
{
    return $this->belongsTo(Brand::class);
}

public function category()
{
    return $this->belongsTo(Category::class);
}
}
