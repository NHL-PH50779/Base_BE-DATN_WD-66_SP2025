<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Test đơn hàng có items ===" . PHP_EOL;

// Tìm đơn hàng có items của user 20
$orderWithItems = App\Models\Order::where('user_id', 20)
    ->whereHas('items')
    ->with(['items.product', 'items.productVariant'])
    ->first();

if (!$orderWithItems) {
    echo "❌ User 20 không có đơn hàng nào có items" . PHP_EOL;
    
    // Tìm đơn hàng có items của user khác
    $anyOrderWithItems = App\Models\Order::whereHas('items')
        ->with(['items.product', 'items.productVariant'])
        ->first();
        
    if ($anyOrderWithItems) {
        echo "✅ Tìm thấy đơn hàng #" . $anyOrderWithItems->id . " của user " . $anyOrderWithItems->user_id . PHP_EOL;
        $orderWithItems = $anyOrderWithItems;
    } else {
        echo "❌ Không có đơn hàng nào có items" . PHP_EOL;
        exit;
    }
}

echo "Đơn hàng #" . $orderWithItems->id . PHP_EOL;
echo "User ID: " . $orderWithItems->user_id . PHP_EOL;
echo "Items: " . $orderWithItems->items->count() . PHP_EOL;
echo PHP_EOL;

// Test transform logic
echo "=== Test Transform Logic ===" . PHP_EOL;
$orderWithItems->items->transform(function($item) {
    $productName = $item->product_name ?: ($item->product ? $item->product->name : 'Sản phẩm đã xóa');
    $productImage = $item->product_image ?: ($item->product && $item->product->thumbnail ? $item->product->thumbnail : 'http://127.0.0.1:8000/placeholder.svg');
    $variantName = $item->variant_name ?: ($item->productVariant ? $item->productVariant->Name : 'Mặc định');
    
    $item->product_info = [
        'id' => $item->product_id,
        'name' => $productName,
        'image' => $productImage,
        'variant_name' => $variantName
    ];
    
    echo "Item #" . $item->id . ":" . PHP_EOL;
    echo "  Product ID: " . $item->product_id . PHP_EOL;
    echo "  Name: " . $productName . PHP_EOL;
    echo "  Image: " . $productImage . PHP_EOL;
    echo "  Variant: " . $variantName . PHP_EOL;
    echo "  Quantity: " . $item->quantity . PHP_EOL;
    echo "  Price: " . number_format($item->price) . " VND" . PHP_EOL;
    echo PHP_EOL;
    
    return $item;
});

echo "✅ Transform thành công!" . PHP_EOL;
echo "URL để test: http://localhost:5174/orders/" . $orderWithItems->id . PHP_EOL;