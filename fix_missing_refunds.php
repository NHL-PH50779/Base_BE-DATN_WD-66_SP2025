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

echo "=== SỬA CHỮA GIAO DỊCH HOÀN TIỀN BỊ THIẾU ===\n";

// Tìm các đơn hàng đã đánh dấu hoàn tiền nhưng không có giao dịch
$orders = DB::table('orders')
    ->where('user_id', 20)
    ->where('payment_status_id', 3) // Đã hoàn tiền
    ->whereIn('order_status_id', [6, 8]) // Cancelled hoặc Return approved
    ->get();

echo "Tìm thấy " . count($orders) . " đơn hàng cần sửa chữa:\n\n";

foreach ($orders as $order) {
    echo "Đơn hàng #{$order->id}: " . number_format($order->total) . " VND\n";
    
    // Kiểm tra đã có giao dịch hoàn tiền chưa
    $existingRefund = DB::table('wallet_transactions')
        ->where('reference_id', $order->id)
        ->where('reference_type', 'order')
        ->where('type', 'credit')
        ->where('user_id', 20)
        ->first();
    
    if ($existingRefund) {
        echo "- Đã có giao dịch hoàn tiền: " . number_format($existingRefund->amount) . " VND\n";
    } else {
        echo "- THIẾU giao dịch hoàn tiền! Đang tạo...\n";
        
        // Lấy số dư hiện tại
        $currentBalance = DB::table('wallets')->where('user_id', 20)->value('balance') ?? 0;
        $newBalance = $currentBalance + $order->total;
        
        // Tạo giao dịch hoàn tiền
        DB::table('wallet_transactions')->insert([
            'user_id' => 20,
            'type' => 'credit',
            'amount' => $order->total,
            'balance_before' => $currentBalance,
            'balance_after' => $newBalance,
            'description' => 'Hoàn tiền đơn hàng #' . $order->id . ' (Sửa chữa)',
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Cập nhật số dư ví
        DB::table('wallets')->updateOrInsert(
            ['user_id' => 20],
            ['balance' => $newBalance, 'updated_at' => now()]
        );
        
        echo "- ✅ Đã tạo giao dịch hoàn tiền: " . number_format($order->total) . " VND\n";
        echo "- ✅ Số dư ví mới: " . number_format($newBalance) . " VND\n";
    }
    
    echo "---\n";
}

// Kiểm tra số dư ví cuối cùng
$finalBalance = DB::table('wallets')->where('user_id', 20)->value('balance') ?? 0;
echo "\n=== KẾT QUẢ CUỐI CÙNG ===\n";
echo "Số dư ví hiện tại: " . number_format($finalBalance) . " VND\n";

// Tổng tất cả giao dịch hoàn tiền
$totalRefunds = DB::table('wallet_transactions')
    ->where('user_id', 20)
    ->where('type', 'credit')
    ->sum('amount');

echo "Tổng tất cả giao dịch hoàn tiền: " . number_format($totalRefunds) . " VND\n";