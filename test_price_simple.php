<?php
require_once 'vendor/autoload.php';

use App\Models\Product;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$testCases = [
    "trên 30 triệu",
    "giúp tôi tìm những sản phẩm trên 30 triệu",
    "sản phẩm trên 25 triệu",
    "dưới 20 triệu"
];

foreach ($testCases as $keyword) {
    echo "\n=== Testing: {$keyword} ===\n";
    
    $minPrice = null;
    $maxPrice = null;
    
    if (preg_match('/(giá\s*)?(trên|>)\s*(\d+)\s*(triệu|tr)/i', $keyword, $matches)) {
        $priceInMillions = (float)$matches[3];
        $minPrice = $priceInMillions * 1000000;
        echo "Min price detected: {$priceInMillions} triệu = {$minPrice} VND\n";
    } elseif (preg_match('/(giá\s*)?(dưới|<)\s*(\d+)\s*(triệu|tr)/i', $keyword, $matches)) {
        $priceInMillions = (float)$matches[3];
        $maxPrice = $priceInMillions * 1000000;
        echo "Max price detected: {$priceInMillions} triệu = {$maxPrice} VND\n";
    } else {
        echo "No price pattern matched\n";
        continue;
    }
    
    // Test query
    $products = Product::with(['variants', 'brand', 'category'])
        ->where('is_active', true)
        ->whereHas('variants', function($q) use ($minPrice, $maxPrice) {
            $q->where('is_active', true);
            if ($minPrice) {
                $q->where('price', '>=', $minPrice);
            }
            if ($maxPrice) {
                $q->where('price', '<=', $maxPrice);
            }
        })
        ->get();
        
    // Filter products
    if ($minPrice || $maxPrice) {
        $products = $products->filter(function ($product) use ($minPrice, $maxPrice) {
            if ($product->variants && $product->variants->count() > 0) {
                $validVariant = $product->variants->first(function($variant) use ($minPrice, $maxPrice) {
                    $price = $variant->price;
                    if ($minPrice && $price < $minPrice) return false;
                    if ($maxPrice && $price > $maxPrice) return false;
                    return true;
                });
                
                if ($validVariant) {
                    $product->price = $validVariant->price;
                    return true;
                }
            }
            return false;
        });
    }
        
    echo "Found " . $products->count() . " products:\n";
    
    foreach ($products->take(3) as $product) {
        echo "- {$product->name}: " . number_format($product->price) . " VND\n";
    }
}