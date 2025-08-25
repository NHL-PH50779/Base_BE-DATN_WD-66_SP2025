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

echo "=== CẤU TRÚC BẢNG WALLET_TRANSACTIONS ===\n";

$columns = DB::select("DESCRIBE wallet_transactions");
foreach ($columns as $column) {
    echo "- {$column->Field}: {$column->Type} " . 
         ($column->Null === 'NO' ? 'NOT NULL' : 'NULL') . 
         ($column->Default ? " DEFAULT {$column->Default}" : '') . "\n";
}

echo "\n=== MẪU GIAO DỊCH HIỆN TẠI ===\n";
$sample = DB::table('wallet_transactions')->where('user_id', 20)->first();
if ($sample) {
    foreach ((array)$sample as $key => $value) {
        echo "- {$key}: {$value}\n";
    }
}