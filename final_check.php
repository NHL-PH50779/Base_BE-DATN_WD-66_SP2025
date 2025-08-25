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

echo "=== KIỂM TRA CUỐI CÙNG ===\n";

// Số dư ví
$balance = DB::table('wallets')->where('user_id', 20)->value('balance') ?? 0;
echo "Số dư ví user 20: " . number_format($balance) . " VND\n\n";

// Tất cả giao dịch hoàn tiền
echo "=== TẤT CẢ GIAO DỊCH HOÀN TIỀN ===\n";
$transactions = DB::table('wallet_transactions')
    ->where('user_id', 20)
    ->where('type', 'credit')
    ->orderBy('created_at', 'desc')
    ->get();

$total = 0;
foreach ($transactions as $tx) {
    echo "- {$tx->description}: " . number_format($tx->amount) . " VND ({$tx->created_at})\n";
    $total += $tx->amount;
}

echo "\nTổng cộng: " . number_format($total) . " VND\n";
echo "Khớp với số dư ví: " . ($total == $balance ? "✅ ĐÚNG" : "❌ SAI") . "\n";