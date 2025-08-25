<?php
require_once 'vendor/autoload.php';

// Test tạo sản phẩm trực tiếp
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== TEST CREATE PRODUCT ===\n";
    
    // Kiểm tra brands và categories
    $brands = Brand::all();
    $categories = Category::all();
    
    echo "Brands available: " . $brands->count() . "\n";
    foreach($brands as $brand) {
        echo "- Brand ID: {$brand->id}, Name: {$brand->name}\n";
    }
    
    echo "\nCategories available: " . $categories->count() . "\n";
    foreach($categories as $category) {
        echo "- Category ID: {$category->id}, Name: {$category->name}\n";
    }
    
    // Thử tạo sản phẩm
    $product = Product::create([
        'name' => 'Test Product ' . time(),
        'description' => 'Test description',
        'brand_id' => $brands->first()->id,
        'category_id' => $categories->first()->id,
        'is_active' => true,
    ]);
    
    echo "\n✅ Product created successfully!\n";
    echo "Product ID: {$product->id}\n";
    echo "Product Name: {$product->name}\n";
    
    // Tạo variant mặc định
    $variant = $product->variants()->create([
        'sku' => 'SKU-' . $product->id . '-DEFAULT',
        'Name' => 'Mặc định',
        'price' => 100000,
        'stock' => 1,
        'quantity' => 1,
        'is_active' => true,
    ]);
    
    echo "✅ Variant created successfully!\n";
    echo "Variant ID: {$variant->id}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}