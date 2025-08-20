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

echo "=== KIỂM TRA ĐỚN HÀNG #159 ===\n";

// Kiểm tra đơn hàng #159
$order = DB::table('orders')->where('id', 159)->first();

if (!$order) {
    echo "❌ Không tìm thấy đơn hàng #159\n";
    exit;
}

echo "Thông tin đơn hàng #159:\n";
echo "- User ID: {$order->user_id}\n";
echo "- Tổng tiền: " . number_format($order->total) . " VND\n";
echo "- Trạng thái đơn: {$order->order_status_id}\n";
echo "- Trạng thái thanh toán: {$order->payment_status_id}\n";
echo "- Phương thức: {$order->payment_method}\n";
echo "- Ngày tạo: {$order->created_at}\n";
echo "- Ngày cập nhật: {$order->updated_at}\n";

// Kiểm tra giao dịch hoàn tiền
$refunds = DB::table('wallet_transactions')
    ->where('reference_id', 159)
    ->where('reference_type', 'order')
    ->where('type', 'credit')
    ->get();

echo "\nGiao dịch hoàn tiền:\n";
if (count($refunds) > 0) {
    foreach ($refunds as $refund) {
        echo "✅ {$refund->description}: " . number_format($refund->amount) . " VND ({$refund->created_at})\n";
    }
} else {
    echo "❌ KHÔNG CÓ giao dịch hoàn tiền nào!\n";
}

// Kiểm tra số dư ví hiện tại
$currentBalance = DB::table('wallets')->where('user_id', $order->user_id)->value('balance') ?? 0;
echo "\nSố dư ví hiện tại: " . number_format($currentBalance) . " VND\n";

// Nếu chưa hoàn tiền và đơn đã hủy + đã thanh toán, thực hiện hoàn tiền
if (count($refunds) == 0 && $order->order_status_id == 6 && $order->payment_status_id == 2) {
    echo "\n=== THỰC HIỆN HOÀN TIỀN ===\n";
    
    $newBalance = $currentBalance + $order->total;
    
    // Tạo giao dịch hoàn tiền
    DB::table('wallet_transactions')->insert([
        'user_id' => $order->user_id,
        'type' => 'credit',
        'amount' => $order->total,
        'balance_before' => $currentBalance,
        'balance_after' => $newBalance,
        'description' => 'Hoàn tiền hủy đơn #' . $order->id . ' (Manual Fix)',
        'reference_type' => 'order',
        'reference_id' => $order->id,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Cập nhật số dư ví
    DB::table('wallets')->where('user_id', $order->user_id)->update([
        'balance' => $newBalance,
        'updated_at' => now()
    ]);
    
    // Cập nhật trạng thái thanh toán
    DB::table('orders')->where('id', 159)->update([
        'payment_status_id' => 3, // Đã hoàn tiền
        'updated_at' => now()
    ]);
    
    echo "✅ Đã hoàn tiền: " . number_format($order->total) . " VND\n";
    echo "✅ Số dư ví mới: " . number_format($newBalance) . " VND\n";
    echo "✅ Đã cập nhật trạng thái thanh toán thành 'Đã hoàn tiền'\n";
} else {
    echo "\nLý do không hoàn tiền:\n";
    if (count($refunds) > 0) echo "- Đã hoàn tiền trước đó\n";
    if ($order->order_status_id != 6) echo "- Đơn hàng chưa bị hủy (status: {$order->order_status_id})\n";
    if ($order->payment_status_id != 2) echo "- Đơn hàng chưa thanh toán (payment_status: {$order->payment_status_id})\n";
}