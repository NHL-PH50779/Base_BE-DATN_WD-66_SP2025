<?php

require_once 'vendor/autoload.php';

use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Test Approve Cancel API ===\n";

// Tìm order ID 103
$order = Order::with('user')->find(103);

if (!$order) {
    echo "Order 103 không tồn tại\n";
    exit;
}

echo "Order ID: {$order->id}\n";
echo "User ID: {$order->user_id}\n";
echo "Total: {$order->total}\n";
echo "Payment Status: {$order->payment_status_id}\n";
echo "Cancel Requested: " . ($order->cancel_requested ? 'Yes' : 'No') . "\n";

if ($order->user) {
    echo "User Name: {$order->user->name}\n";
    
    // Kiểm tra wallet
    $wallet = $order->user->wallet;
    if ($wallet) {
        echo "Wallet Balance: {$wallet->balance}\n";
    } else {
        echo "User chưa có wallet\n";
    }
    
    // Kiểm tra transactions
    $transactions = WalletTransaction::where('reference_id', $order->id)
        ->where('reference_type', 'order')
        ->where('type', 'credit')
        ->where('description', 'LIKE', '%Hoàn tiền%')
        ->get();
    
    echo "Existing refund transactions: " . $transactions->count() . "\n";
    foreach ($transactions as $trans) {
        echo "  - Transaction ID: {$trans->id}, Amount: {$trans->amount}\n";
    }
} else {
    echo "Order không có user\n";
}

echo "\n=== Test hoàn tiền ===\n";

try {
    // Simulate approve cancel
    if (!$order->user) {
        throw new Exception("Order không có user");
    }
    
    $wallet = $order->user->wallet;
    if (!$wallet) {
        $wallet = $order->user->wallet()->create(['balance' => 0]);
        echo "Đã tạo wallet mới\n";
    }
    
    // Kiểm tra đã hoàn tiền chưa
    $alreadyRefunded = WalletTransaction::where('reference_id', $order->id)
        ->where('reference_type', 'order')
        ->where('type', 'credit')
        ->where('description', 'LIKE', '%Hoàn tiền%')
        ->exists();
    
    if ($alreadyRefunded) {
        echo "Đơn này đã hoàn tiền trước đó\n";
    } else {
        echo "Chưa hoàn tiền, có thể thực hiện hoàn tiền\n";
        
        // Test tạo transaction
        $balanceBefore = $wallet->balance;
        echo "Balance before: {$balanceBefore}\n";
        
        // Không thực sự update, chỉ test
        echo "Sẽ hoàn tiền: {$order->total}\n";
        echo "Balance after sẽ là: " . ($balanceBefore + $order->total) . "\n";
    }
    
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}

echo "\n=== Kiểm tra cấu trúc bảng ===\n";

// Kiểm tra cấu trúc bảng wallet_transactions
$columns = DB::select("DESCRIBE wallet_transactions");
echo "Cấu trúc bảng wallet_transactions:\n";
foreach ($columns as $column) {
    echo "  - {$column->Field}: {$column->Type}\n";
}