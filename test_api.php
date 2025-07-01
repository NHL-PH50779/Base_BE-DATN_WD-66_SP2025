<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->bootstrap();

use App\Models\Product;

echo "=== TEST API FILTER ===\n";

// Test filter by brand_id = 19 (Acer)
echo "Products for Acer (brand_id = 19):\n";
$acerProducts = Product::with(['brand', 'category'])
    ->where('brand_id', 19)
    ->where('is_active', true)
    ->get();

echo "Count: " . $acerProducts->count() . "\n";
foreach ($acerProducts as $product) {
    $active = $product->is_active ? 'YES' : 'NO';
    echo "- {$product->name} | Active: {$active} | Brand: {$product->brand->name}\n";
}

echo "\n=== BRAND 19 PRODUCTS COUNT ===\n";
$brand = \App\Models\Brand::withCount(['products' => function ($query) {
    $query->where('is_active', true);
}])->find(19);

echo "Brand: {$brand->name} | Products Count: {$brand->products_count}\n";