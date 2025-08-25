<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Tìm đơn hàng có sản phẩm ===" . PHP_EOL;

// Tìm các đơn hàng có items
$ordersWithItems = App\Models\Order::whereHas('items')->with('items')->take(5)->get();

if ($ordersWithItems->count() == 0) {
    echo "❌ Không có đơn hàng nào có sản phẩm" . PHP_EOL;
    
    // Kiểm tra tổng số đơn hàng
    $totalOrders = App\Models\Order::count();
    echo "Tổng số đơn hàng: " . $totalOrders . PHP_EOL;
    
    // Kiểm tra tổng số order_items
    $totalItems = App\Models\OrderItem::count();
    echo "Tổng số order_items: " . $totalItems . PHP_EOL;
    
    exit;
}

echo "✅ Tìm thấy " . $ordersWithItems->count() . " đơn hàng có sản phẩm" . PHP_EOL;
echo PHP_EOL;

foreach ($ordersWithItems as $order) {
    echo "--- Đơn hàng #" . $order->id . " ---" . PHP_EOL;
    echo "User ID: " . $order->user_id . PHP_EOL;
    echo "Total: " . number_format($order->total) . " VND" . PHP_EOL;
    echo "Items: " . $order->items->count() . PHP_EOL;
    
    foreach ($order->items as $item) {
        echo "  - Product ID: " . $item->product_id . PHP_EOL;
        echo "    Name (saved): " . ($item->product_name ?: 'NULL') . PHP_EOL;
        echo "    Image (saved): " . ($item->product_image ?: 'NULL') . PHP_EOL;
        echo "    Variant (saved): " . ($item->variant_name ?: 'NULL') . PHP_EOL;
    }
    echo PHP_EOL;
}

// Tìm đơn hàng của user 20 (user của đơn 184)
echo "=== Đơn hàng của User 20 ===" . PHP_EOL;
$user20Orders = App\Models\Order::where('user_id', 20)->with('items')->get();
echo "User 20 có " . $user20Orders->count() . " đơn hàng" . PHP_EOL;

foreach ($user20Orders as $order) {
    echo "Đơn #" . $order->id . " - Items: " . $order->items->count() . PHP_EOL;
}