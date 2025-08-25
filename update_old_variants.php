<?php
require_once 'vendor/autoload.php';

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Attribute;
use App\Models\AttributeValue;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "=== CẬP NHẬT BIẾN THỂ CŨ ===\n";
    
    // Tìm các biến thể có tên chứa màu sắc cũ
    $oldVariants = ProductVariant::where('Name', 'like', '%Gray%')
        ->orWhere('Name', 'like', '%Silver%')
        ->orWhere('Name', 'like', '%Black%')
        ->orWhere('Name', 'like', '%White%')
        ->orWhere('Name', 'like', '%Gold%')
        ->get();
    
    echo "Tìm thấy " . $oldVariants->count() . " biến thể cũ có màu sắc\n";
    
    foreach($oldVariants as $variant) {
        echo "\n--- Biến thể ID: {$variant->id} ---\n";
        echo "Tên cũ: {$variant->Name}\n";
        
        // Thay thế màu sắc bằng CPU
        $newName = $variant->Name;
        $newName = str_replace(['Gray', 'Silver', 'Black', 'White', 'Gold'], 'Intel', $newName);
        
        // Cập nhật tên biến thể
        $variant->update(['Name' => $newName]);
        
        echo "Tên mới: {$newName}\n";
        echo "✅ Đã cập nhật\n";
    }
    
    echo "\n=== THỐNG KÊ SAU CẬP NHẬT ===\n";
    
    // Hiển thị tất cả biến thể hiện tại
    $allVariants = ProductVariant::with('product')->get();
    
    foreach($allVariants as $variant) {
        echo "- Product: {$variant->product->name} | Variant: {$variant->Name}\n";
    }
    
    echo "\n✅ Hoàn thành cập nhật biến thể!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}