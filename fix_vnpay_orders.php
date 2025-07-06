<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Fixing VNPay orders status...\n";

// Fix orders with payment_status = 'paid' but wrong IDs
$updated1 = DB::table('orders')
    ->where('payment_method', 'vnpay')
    ->where('payment_status', 'paid')
    ->where(function($query) {
        $query->where('payment_status_id', '!=', 2)
              ->orWhere('order_status_id', '!=', 2);
    })
    ->update([
        'payment_status_id' => 2,
        'order_status_id' => 2
    ]);

echo "Updated {$updated1} paid VNPay orders\n";

// Fix orders with status = 'confirmed' but payment_status = 'unpaid' for VNPay
$updated2 = DB::table('orders')
    ->where('payment_method', 'vnpay')
    ->where('status', 'confirmed')
    ->where('payment_status', 'unpaid')
    ->update([
        'payment_status' => 'paid',
        'payment_status_id' => 2,
        'order_status_id' => 2
    ]);

echo "Updated {$updated2} confirmed VNPay orders to paid status\n";

echo "Done!\n";