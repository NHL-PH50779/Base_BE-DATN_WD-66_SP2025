<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Cập nhật thông tin sản phẩm cho order_items cũ ===" . PHP_EOL;

// Lấy tất cả order_items chưa có thông tin sản phẩm
$itemsToUpdate = App\Models\OrderItem::whereNull('product_name')
    ->with(['product', 'productVariant'])
    ->get();

echo "Tìm thấy " . $itemsToUpdate->count() . " items cần cập nhật" . PHP_EOL;

$updated = 0;
$failed = 0;

foreach ($itemsToUpdate as $item) {
    try {
        $productName = 'Sản phẩm đã xóa';
        $productImage = 'http://127.0.0.1:8000/placeholder.svg';
        $variantName = 'Mặc định';
        
        // Lấy thông tin từ relationship nếu còn tồn tại
        if ($item->product) {
            $productName = $item->product->name;
            $productImage = $item->product->thumbnail ?: 'http://127.0.0.1:8000/placeholder.svg';
        }
        
        if ($item->productVariant) {
            $variantName = $item->productVariant->Name;
        }
        
        // Cập nhật
        $item->update([
            'product_name' => $productName,
            'product_image' => $productImage,
            'variant_name' => $variantName
        ]);
        
        $updated++;
        
        if ($updated % 10 == 0) {
            echo "Đã cập nhật " . $updated . " items..." . PHP_EOL;
        }
        
    } catch (Exception $e) {
        echo "Lỗi cập nhật item #" . $item->id . ": " . $e->getMessage() . PHP_EOL;
        $failed++;
    }
}

echo PHP_EOL;
echo "✅ Hoàn thành!" . PHP_EOL;
echo "- Đã cập nhật: " . $updated . " items" . PHP_EOL;
echo "- Thất bại: " . $failed . " items" . PHP_EOL;

// Test một vài items sau khi cập nhật
echo PHP_EOL;
echo "=== Kiểm tra kết quả ===" . PHP_EOL;
$sampleItems = App\Models\OrderItem::whereNotNull('product_name')->take(3)->get();
foreach ($sampleItems as $item) {
    echo "Item #" . $item->id . ":" . PHP_EOL;
    echo "  Name: " . $item->product_name . PHP_EOL;
    echo "  Image: " . $item->product_image . PHP_EOL;
    echo "  Variant: " . $item->variant_name . PHP_EOL;
    echo PHP_EOL;
}