<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
    'product_id',
    'sku',
    'Name',      // Uppercase N
    'price',
    'stock',
    'quantity',
    'is_active'
];

    public function product()
{
    return $this->belongsTo(Product::class);
}

public function attributeValues()
{
    return $this->belongsToMany(AttributeValue::class, 'product_variant_attribute_value');
}

}



