<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'datn_sp2025_p',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== Fix Balance Correct ===\n\n";

try {
    $user = $capsule->table('users')->where('email', 'phamdiemle3110@gmail.com')->first();
    $wallet = $capsule->table('wallets')->where('user_id', $user->id)->first();
    
    echo "User: {$user->name} (ID: {$user->id})\n";
    echo "Current Balance: " . number_format($wallet->balance) . " VND\n\n";
    
    // Lấy transactions theo user_id (đúng theo model)
    $transactions = $capsule->table('wallet_transactions')
        ->where('user_id', $user->id)
        ->orderBy('created_at', 'asc')
        ->get();
    
    echo "=== All Transactions ===\n";
    $runningBalance = 0;
    
    foreach ($transactions as $tx) {
        if ($tx->type === 'credit') {
            $runningBalance += $tx->amount;
            echo "✅ +{$tx->amount} - {$tx->description}\n";
        } else {
            $runningBalance -= $tx->amount;
            echo "❌ -{$tx->amount} - {$tx->description}\n";
        }
        echo "   Running Balance: " . number_format($runningBalance) . " VND\n\n";
    }
    
    echo "=== Summary ===\n";
    echo "Calculated Balance: " . number_format($runningBalance) . " VND\n";
    echo "Database Balance: " . number_format($wallet->balance) . " VND\n";
    
    if ($runningBalance != $wallet->balance) {
        echo "\n⚠️  Balance không khớp! Đang cập nhật...\n";
        
        $capsule->table('wallets')
            ->where('id', $wallet->id)
            ->update([
                'balance' => $runningBalance,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        echo "✅ Đã cập nhật balance từ " . number_format($wallet->balance) . " VND thành " . number_format($runningBalance) . " VND\n";
        
        // Clear cache nếu có
        echo "🔄 Vui lòng refresh trang wallet để thấy thay đổi\n";
    } else {
        echo "✅ Balance đã chính xác!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}