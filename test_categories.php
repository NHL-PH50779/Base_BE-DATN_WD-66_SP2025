<?php
require_once 'vendor/autoload.php';

use App\Models\Product;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$testCases = [
    "laptop chơi game",
    "laptop đồ họa", 
    "laptop văn phòng",
    "sản phẩm laptop gaming",
    "danh mục laptop đồ họa"
];

foreach ($testCases as $keyword) {
    echo "\n=== Testing: {$keyword} ===\n";
    
    if (preg_match('/(danh mục|sản phẩm).*(laptop.*chơi game|laptop.*gaming|chơi game|gaming|laptop.*đồ họa|đồ họa|laptop.*văn phòng|văn phòng|laptop|máy tính)/i', $keyword, $matches)) {
        echo "Category detected: " . $matches[2] . "\n";
        
        $categoryKeyword = strtolower($matches[2]);
        
        $products = Product::with(['variants', 'brand', 'category'])
            ->where('is_active', true)
            ->whereHas('category', function($q) use ($categoryKeyword) {
                if (strpos($categoryKeyword, 'chơi game') !== false || strpos($categoryKeyword, 'gaming') !== false) {
                    $q->where('name', 'like', '%gaming%')
                      ->orWhere('name', 'like', '%chơi game%');
                } elseif (strpos($categoryKeyword, 'đồ họa') !== false) {
                    $q->where('name', 'like', '%đồ họa%')
                      ->orWhere('name', 'like', '%kỹ thuật%')
                      ->orWhere('name', 'like', '%design%');
                } elseif (strpos($categoryKeyword, 'văn phòng') !== false) {
                    $q->where('name', 'like', '%văn phòng%')
                      ->orWhere('name', 'like', '%office%')
                      ->orWhere('name', 'like', '%học tập%');
                } else {
                    $q->where('name', 'like', '%laptop%')
                      ->orWhere('name', 'like', '%máy tính%');
                }
            })
            ->get();
            
        echo "Found " . $products->count() . " products:\n";
        
        foreach ($products->take(3) as $product) {
            echo "- {$product->name} (Category: {$product->category->name})\n";
        }
    } else {
        echo "No category match\n";
    }
}