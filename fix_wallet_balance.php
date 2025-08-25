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

echo "=== Fix Wallet Balance ===\n\n";

try {
    $user = $capsule->table('users')->where('email', 'phamdiemle3110@gmail.com')->first();
    $wallet = $capsule->table('wallets')->where('user_id', $user->id)->first();
    
    echo "User: {$user->name}\n";
    echo "Current Balance: " . number_format($wallet->balance) . " VND\n\n";
    
    // Lấy tất cả transactions của wallet này
    $transactions = $capsule->table('wallet_transactions')
        ->join('wallets', 'wallet_transactions.wallet_id', '=', 'wallets.id')
        ->where('wallets.user_id', $user->id)
        ->select('wallet_transactions.*')
        ->orderBy('wallet_transactions.created_at', 'asc')
        ->get();
    
    echo "=== All Transactions ===\n";
    $runningBalance = 0;
    
    foreach ($transactions as $tx) {
        if ($tx->type === 'credit') {
            $runningBalance += $tx->amount;
            echo "✅ +{$tx->amount} - {$tx->description} (Balance: {$runningBalance})\n";
        } else {
            $runningBalance -= $tx->amount;
            echo "❌ -{$tx->amount} - {$tx->description} (Balance: {$runningBalance})\n";
        }
    }
    
    echo "\n=== Summary ===\n";
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
    } else {
        echo "✅ Balance đã chính xác!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}