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

echo "=== TEST LUỒNG HOÀN TIỀN ===\n";

// Số dư ví trước khi test
$balanceBefore = DB::table('wallets')->where('user_id', 20)->value('balance') ?? 0;
echo "Số dư ví trước test: " . number_format($balanceBefore) . " VND\n";

// Tạo đơn hàng test
$testOrderId = DB::table('orders')->insertGetId([
    'user_id' => 20,
    'total' => 500000,
    'name' => 'Test User',
    'phone' => '0123456789',
    'address' => 'Test Address',
    'payment_method' => 'vnpay',
    'order_status_id' => 2, // Confirmed
    'payment_status_id' => 2, // Paid
    'status' => 'confirmed',
    'payment_status' => 'paid',
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Đã tạo đơn hàng test #$testOrderId: 500,000 VND\n";

// Simulate hủy đơn hàng qua API
echo "\n=== SIMULATE HỦY ĐƠN HÀNG ===\n";

// Lấy thông tin đơn hàng
$order = DB::table('orders')->where('id', $testOrderId)->first();

// Simulate logic hủy đơn từ OrderController
if ($order->payment_status_id == 2) { // Đã thanh toán
    // Lấy ví user
    $currentBalance = DB::table('wallets')->where('user_id', 20)->value('balance') ?? 0;
    $newBalance = $currentBalance + $order->total;
    
    // Tạo giao dịch hoàn tiền
    DB::table('wallet_transactions')->insert([
        'user_id' => 20,
        'type' => 'credit',
        'amount' => $order->total,
        'balance_before' => $currentBalance,
        'balance_after' => $newBalance,
        'description' => 'Hoàn tiền hủy đơn #' . $order->id . ' (TEST)',
        'reference_type' => 'order',
        'reference_id' => $order->id,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Cập nhật số dư ví
    DB::table('wallets')->where('user_id', 20)->update([
        'balance' => $newBalance,
        'updated_at' => now()
    ]);
    
    // Cập nhật trạng thái đơn hàng
    DB::table('orders')->where('id', $testOrderId)->update([
        'order_status_id' => 6, // Cancelled
        'payment_status_id' => 3, // Refunded
        'updated_at' => now()
    ]);
    
    echo "✅ Đã hoàn tiền: " . number_format($order->total) . " VND\n";
    echo "✅ Số dư ví mới: " . number_format($newBalance) . " VND\n";
} else {
    echo "❌ Đơn hàng chưa thanh toán, không hoàn tiền\n";
}

// Kiểm tra kết quả
$balanceAfter = DB::table('wallets')->where('user_id', 20)->value('balance') ?? 0;
$difference = $balanceAfter - $balanceBefore;

echo "\n=== KẾT QUẢ TEST ===\n";
echo "Số dư trước: " . number_format($balanceBefore) . " VND\n";
echo "Số dư sau: " . number_format($balanceAfter) . " VND\n";
echo "Chênh lệch: " . number_format($difference) . " VND\n";
echo "Kết quả: " . ($difference == 500000 ? "✅ THÀNH CÔNG" : "❌ THẤT BẠI") . "\n";

// Xóa đơn hàng test
DB::table('orders')->where('id', $testOrderId)->delete();
echo "\nĐã xóa đơn hàng test #$testOrderId\n";