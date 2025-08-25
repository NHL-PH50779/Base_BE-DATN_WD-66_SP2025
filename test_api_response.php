<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Test API Response Structure ===" . PHP_EOL;

// Tìm đơn hàng có items
$order = App\Models\Order::whereHas('items')->with(['items.product', 'items.productVariant'])->first();

if (!$order) {
    echo "❌ Không có đơn hàng nào có items" . PHP_EOL;
    exit;
}

echo "Testing với đơn hàng #" . $order->id . PHP_EOL;

// Transform items như trong controller
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
    return $item;
});

// Tạo response giống như API
$response = [
    'message' => 'Chi tiết đơn hàng',
    'data' => $order
];

echo PHP_EOL;
echo "=== Response Structure ===" . PHP_EOL;
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo PHP_EOL;

echo PHP_EOL;
echo "=== Key Fields Check ===" . PHP_EOL;
echo "order.id: " . ($order->id ?? 'NULL') . PHP_EOL;
echo "order.total: " . ($order->total ?? 'NULL') . PHP_EOL;
echo "order.coupon_discount: " . ($order->coupon_discount ?? 'NULL') . PHP_EOL;
echo "order.coupon_code: " . ($order->coupon_code ?? 'NULL') . PHP_EOL;
echo "order.items.count: " . $order->items->count() . PHP_EOL;