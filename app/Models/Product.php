<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at']; // Không bắt buộc nếu dùng Laravel 8+

    protected $fillable = [ 'name', 'description', 'price', 'stock', 'brand_id', 'category_id', 'thumbnail','cloudinary_public_id', 'is_active' ];

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

public function wishlists()
{
    return $this->hasMany(Wishlist::class);
}

public function comments()
{
    return $this->hasMany(Comment::class);
}

// Accessor để tự động xử lý ảnh
public function getThumbnailAttribute($value)
{
    // Kiểm tra các trường hợp cần thay thế
    if (!$value || 
        trim($value) === '' ||
        $value === 'null' ||
        $value === 'No image' ||
        $value === 'Noimage' ||
        stripos($value, 'no image') !== false ||
        stripos($value, 'noimage') !== false) {
        return 'http://127.0.0.1:8000/placeholder.svg';
    }
    
    return $value;
}
}
