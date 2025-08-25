<?php
require_once 'vendor/autoload.php';

use App\Models\Product;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $keyword = "giúp tôi tìm sản phẩm giá trên 20 triệu";
    
    // Test regex
    if (preg_match('/giá\s*(trên|>)\s*(\d+)\s*(triệu|tr)/i', $keyword, $matches)) {
        $priceInMillions = (float)$matches[2];
        $minPrice = $priceInMillions * 1000000;
        
        echo "Detected price search: {$priceInMillions} triệu = {$minPrice} VND\n";
        
        // Test query
        $products = Product::with(['variants', 'brand', 'category'])
            ->where('is_active', true)
            ->whereHas('variants', function($q) use ($minPrice) {
                $q->where('is_active', true);
                $q->where('price', '>=', $minPrice);
            })
            ->get();
            
        echo "Found " . $products->count() . " products\n";
        
        // Áp dụng logic lọc mới
        $filteredProducts = $products->filter(function ($product) use ($minPrice) {
            if ($product->variants && $product->variants->count() > 0) {
                $validVariant = $product->variants->first(function($variant) use ($minPrice) {
                    return $variant->price >= $minPrice;
                });
                
                if ($validVariant) {
                    $product->price = $validVariant->price;
                    return true;
                }
            }
            return false;
        });
        
        echo "After filtering: " . $filteredProducts->count() . " products\n";
        
        foreach ($filteredProducts as $product) {
            echo "- {$product->name}: " . number_format($product->price) . " VND\n";
        }
    } else {
        echo "Regex not matched\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}