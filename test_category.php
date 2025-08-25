<?php
require_once 'vendor/autoload.php';

use App\Models\Product;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $keyword = "giúp tôi xem những sản phẩm trong danh mục laptop chơi game";
    
    echo "Testing keyword: {$keyword}\n";
    
    // Test regex for category
    if (preg_match('/(danh mục|sản phẩm).*(laptop.*chơi game|laptop.*gaming|gaming|chơi game|laptop|máy tính|văn phòng|học tập)/i', $keyword, $matches)) {
        echo "Category detected: " . $matches[2] . "\n";
        
        $categoryKeyword = strtolower($matches[2]);
        
        // Test query
        $products = Product::with(['variants', 'brand', 'category'])
            ->where('is_active', true)
            ->whereHas('category', function($q) use ($categoryKeyword) {
                if (in_array($categoryKeyword, ['laptop', 'máy tính'])) {
                    $q->where('name', 'like', '%laptop%')
                      ->orWhere('name', 'like', '%máy tính%');
                } elseif (in_array($categoryKeyword, ['gaming', 'chơi game'])) {
                    $q->where('name', 'like', '%gaming%')
                      ->orWhere('name', 'like', '%game%')
                      ->orWhere('name', 'like', '%chơi game%');
                }
            })
            ->get();
            
        echo "Found " . $products->count() . " products\n";
        
        foreach ($products as $product) {
            if ($product->variants && $product->variants->count() > 0) {
                $product->price = $product->variants->first()->price;
                echo "- {$product->name}: " . number_format($product->price) . " VND (Category: {$product->category->name})\n";
            }
        }
    } else {
        echo "Category regex not matched\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}