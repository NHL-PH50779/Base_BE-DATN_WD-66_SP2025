<?php
require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Kết nối database
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'datn_sp2025_p',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== TÌM ĐƠN HÀNG LAPTOP 22 TRIỆU ===\n";

// Tìm đơn hàng có giá trị khoảng 22 triệu
$orders = DB::table('orders')
    ->where('total', '>=', 20000000)
    ->where('total', '<=', 25000000)
    ->orderBy('created_at', 'desc')
    ->get();

echo "Số đơn hàng từ 20-25 triệu: " . count($orders) . "\n\n";

foreach ($orders as $order) {
    echo "Đơn hàng #{$order->id}:\n";
    echo "- Tổng tiền: " . number_format($order->total) . " VND\n";
    echo "- User ID: {$order->user_id}\n";
    echo "- Trạng thái đơn: {$order->order_status_id}\n";
    echo "- Trạng thái thanh toán: {$order->payment_status_id}\n";
    echo "- Phương thức: {$order->payment_method}\n";
    echo "- Ngày tạo: {$order->created_at}\n";
    echo "- Yêu cầu hủy: " . ($order->cancel_requested ? 'Có' : 'Không') . "\n";
    
    // Kiểm tra giao dịch hoàn tiền
    $refunds = DB::table('wallet_transactions')
        ->where('reference_id', $order->id)
        ->where('reference_type', 'order')
        ->where('type', 'credit')
        ->get();
    
    echo "- Số giao dịch hoàn tiền: " . count($refunds) . "\n";
    
    if (count($refunds) > 0) {
        foreach ($refunds as $refund) {
            echo "  + {$refund->description}: " . number_format($refund->amount) . " VND\n";
        }
    }
    
    echo "---\n";
}

// Kiểm tra tất cả giao dịch hoàn tiền của user 20
echo "\n=== TẤT CẢ GIAO DỊCH HOÀN TIỀN CỦA USER 20 ===\n";
$allRefunds = DB::table('wallet_transactions')
    ->where('user_id', 20)
    ->where('type', 'credit')
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($allRefunds as $refund) {
    echo "- {$refund->description}: " . number_format($refund->amount) . " VND ({$refund->created_at})\n";
}

echo "\nTổng số giao dịch hoàn tiền: " . count($allRefunds) . "\n";
echo "Tổng tiền hoàn: " . number_format($allRefunds->sum('amount')) . " VND\n";