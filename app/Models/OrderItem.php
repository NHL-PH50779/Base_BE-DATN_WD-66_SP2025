<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'quantity', 'price',
        'product_name', 'product_image', 'variant_name'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
    
    // Accessor để hiển thị thông tin sản phẩm ngay cả khi đã bị xóa
    public function getProductInfoAttribute()
    {
        return [
            'id' => $this->product_id,
            'name' => $this->product_name ?: ($this->product ? $this->product->name : 'Sản phẩm đã xóa'),
            'image' => $this->product_image ?: ($this->product ? $this->product->thumbnail : null),
            'variant_name' => $this->variant_name ?: ($this->productVariant ? $this->productVariant->Name : null)
        ];
    }
}
