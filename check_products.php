<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

use App\Models\Product;

echo "=== CHECKING PRODUCTS ===\n";

$products = Product::withTrashed()->get(['id', 'name', 'brand_id', 'is_active', 'deleted_at']);

foreach ($products as $product) {
    echo "ID: {$product->id} | Name: {$product->name} | Active: " . ($product->is_active ? 'YES' : 'NO') . " | Deleted: " . ($product->deleted_at ? 'YES' : 'NO') . "\n";
}

echo "\n=== ACTIVE PRODUCTS ONLY ===\n";
$activeProducts = Product::where('is_active', true)->get(['id', 'name', 'brand_id']);
echo "Count: " . $activeProducts->count() . "\n";

foreach ($activeProducts as $product) {
    echo "ID: {$product->id} | Name: {$product->name} | Brand ID: {$product->brand_id}\n";
}