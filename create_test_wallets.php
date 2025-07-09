<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Creating Test Wallets ===\n\n";

// Tạo ví cho user ID 10, 14, 15
$userIds = [10, 14, 15];

foreach ($userIds as $userId) {
    $user = User::find($userId);
    if ($user) {
        echo "Creating wallet for User ID: {$userId} - Name: {$user->name}\n";
        
        // Tạo ví với số dư ngẫu nhiên
        $randomBalance = rand(100000, 5000000);
        $wallet = Wallet::create([
            'user_id' => $userId,
            'balance' => $randomBalance
        ]);
        
        // Tạo transaction khởi tạo
        WalletTransaction::create([
            'user_id' => $userId,
            'type' => 'credit',
            'amount' => $randomBalance,
            'balance_before' => 0,
            'balance_after' => $randomBalance,
            'description' => 'Khởi tạo ví',
            'reference_type' => 'system',
            'reference_id' => null
        ]);
        
        echo "  Created wallet with balance: {$randomBalance} VND\n";
    }
}

echo "\n=== Updated Wallet Summary ===\n";
$users = User::with('wallet')->take(5)->get();

foreach ($users as $user) {
    echo "User ID: {$user->id} - Name: {$user->name}\n";
    if ($user->wallet) {
        echo "  Balance: {$user->wallet->balance} VND\n";
    } else {
        echo "  No wallet\n";
    }
}