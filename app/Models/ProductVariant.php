<?php

namespace App\Models;
// App\Models\ProductVariant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'price', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
