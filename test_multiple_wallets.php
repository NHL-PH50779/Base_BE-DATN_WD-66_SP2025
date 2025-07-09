<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Test Multiple User Wallets ===\n\n";

// Lấy một số user để test
$users = User::take(5)->get();

foreach ($users as $user) {
    echo "User ID: {$user->id} - Name: {$user->name}\n";
    
    $wallet = $user->wallet;
    if ($wallet) {
        echo "  Wallet Balance: {$wallet->balance} VND\n";
        
        // Đếm số giao dịch
        $transactionCount = WalletTransaction::where('user_id', $user->id)->count();
        echo "  Transactions: {$transactionCount}\n";
        
        // Hiển thị giao dịch gần nhất
        $latestTransaction = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($latestTransaction) {
            echo "  Latest Transaction: {$latestTransaction->description} - {$latestTransaction->amount} VND\n";
        }
    } else {
        echo "  No wallet found\n";
    }
    echo "  ---\n";
}

echo "\n=== Wallet Summary ===\n";
$totalWallets = Wallet::count();
$totalBalance = Wallet::sum('balance');
$totalTransactions = WalletTransaction::count();

echo "Total Wallets: {$totalWallets}\n";
echo "Total Balance: {$totalBalance} VND\n";
echo "Total Transactions: {$totalTransactions}\n";