<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Kiểm tra đơn hàng 184 ===" . PHP_EOL;

$order = App\Models\Order::with('items')->find(184);
if (!$order) {
    echo "❌ Không tìm thấy đơn hàng 184" . PHP_EOL;
    exit;
}

echo "✅ Tìm thấy đơn hàng 184" . PHP_EOL;
echo "User ID: " . $order->user_id . PHP_EOL;
echo "Total: " . number_format($order->total) . " VND" . PHP_EOL;
echo "Status: " . $order->order_status_id . PHP_EOL;
echo "Items count: " . $order->items->count() . PHP_EOL;
echo PHP_EOL;

if ($order->items->count() == 0) {
    echo "❌ Đơn hàng không có sản phẩm nào" . PHP_EOL;
    exit;
}

echo "=== Chi tiết sản phẩm ===" . PHP_EOL;
foreach ($order->items as $index => $item) {
    echo "--- Item " . ($index + 1) . " ---" . PHP_EOL;
    echo "ID: " . $item->id . PHP_EOL;
    echo "Product ID: " . $item->product_id . PHP_EOL;
    echo "Quantity: " . $item->quantity . PHP_EOL;
    echo "Price: " . number_format($item->price) . " VND" . PHP_EOL;
    
    // Kiểm tra thông tin đã lưu
    echo "Product Name (saved): " . ($item->product_name ?: '❌ NULL') . PHP_EOL;
    echo "Product Image (saved): " . ($item->product_image ?: '❌ NULL') . PHP_EOL;
    echo "Variant Name (saved): " . ($item->variant_name ?: '❌ NULL') . PHP_EOL;
    
    // Kiểm tra relationship
    if ($item->product) {
        echo "Product (relationship): ✅ " . $item->product->name . PHP_EOL;
        echo "Product Image (relationship): " . ($item->product->thumbnail ?: '❌ NULL') . PHP_EOL;
    } else {
        echo "Product (relationship): ❌ NULL (đã bị xóa)" . PHP_EOL;
    }
    
    if ($item->productVariant) {
        echo "Variant (relationship): ✅ " . $item->productVariant->Name . PHP_EOL;
    } else {
        echo "Variant (relationship): ❌ NULL" . PHP_EOL;
    }
    
    echo PHP_EOL;
}

// Test transform logic
echo "=== Test Transform Logic ===" . PHP_EOL;
$order->items->transform(function($item) {
    $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
    $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
    $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
    
    $item->product_info = [
        'id' => $item->product_id,
        'name' => $productName,
        'image' => $productImage,
        'variant_name' => $variantName
    ];
    
    echo "Transform result:" . PHP_EOL;
    echo "- Name: " . $productName . PHP_EOL;
    echo "- Image: " . $productImage . PHP_EOL;
    echo "- Variant: " . $variantName . PHP_EOL;
    echo PHP_EOL;
    
    return $item;
});

echo "✅ Transform hoàn thành" . PHP_EOL;