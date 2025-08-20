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

echo "=== Debug Wallet User phamdiemle3110@gmail.com ===\n\n";

try {
    // Tìm user
    $user = $capsule->table('users')->where('email', 'phamdiemle3110@gmail.com')->first();
    if (!$user) {
        echo "❌ Không tìm thấy user\n";
        return;
    }
    
    echo "✅ User: {$user->name} (ID: {$user->id})\n";
    
    // Kiểm tra wallet
    $wallet = $capsule->table('wallets')->where('user_id', $user->id)->first();
    if (!$wallet) {
        echo "❌ Không có wallet\n";
        return;
    }
    
    echo "✅ Wallet Balance: " . number_format($wallet->balance) . " VND\n";
    echo "   Created: {$wallet->created_at}\n";
    echo "   Updated: {$wallet->updated_at}\n\n";
    
    // Lấy transactions
    $transactions = $capsule->table('wallet_transactions')
        ->where('wallet_id', $wallet->id)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    
    echo "=== Recent Transactions ===\n";
    $totalCredit = 0;
    $totalDebit = 0;
    
    foreach ($transactions as $tx) {
        $sign = $tx->type === 'credit' ? '+' : '-';
        $color = $tx->type === 'credit' ? '✅' : '❌';
        echo "{$color} {$sign}" . number_format($tx->amount) . " VND - {$tx->description}\n";
        echo "   Balance: " . number_format($tx->balance_after) . " VND ({$tx->created_at})\n\n";
        
        if ($tx->type === 'credit') $totalCredit += $tx->amount;
        else $totalDebit += $tx->amount;
    }
    
    echo "=== Summary ===\n";
    echo "Total Credit: " . number_format($totalCredit) . " VND\n";
    echo "Total Debit: " . number_format($totalDebit) . " VND\n";
    echo "Current Balance: " . number_format($wallet->balance) . " VND\n";
    
    // Tính toán lại balance
    $calculatedBalance = $totalCredit - $totalDebit;
    echo "Calculated Balance: " . number_format($calculatedBalance) . " VND\n";
    
    if ($calculatedBalance != $wallet->balance) {
        echo "⚠️  Balance không khớp! Cần cập nhật.\n";
        
        // Cập nhật balance
        $capsule->table('wallets')
            ->where('id', $wallet->id)
            ->update([
                'balance' => $calculatedBalance,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        
        echo "✅ Đã cập nhật balance thành " . number_format($calculatedBalance) . " VND\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}