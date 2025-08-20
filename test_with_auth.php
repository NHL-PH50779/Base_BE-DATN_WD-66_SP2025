<?php

// Test với authentication
$baseUrl = 'http://localhost:8000/api';

// Tạo một user admin test
echo "=== Tạo user admin test ===\n";

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Khởi tạo database connection
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

try {
    // Kiểm tra xem có user admin nào không
    $adminUser = $capsule->table('users')->where('role', 'admin')->first();
    
    if (!$adminUser) {
        echo "Tạo user admin mới...\n";
        $userId = $capsule->table('users')->insertGetId([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'admin',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Đã tạo user admin với ID: $userId\n";
    } else {
        echo "✅ User admin đã tồn tại: " . $adminUser->email . "\n";
    }
    
    // Tạo một withdraw request test
    $testUserId = $adminUser ? $adminUser->id : $userId;
    
    $withdrawId = $capsule->table('withdraw_requests')->insertGetId([
        'user_id' => $testUserId,
        'amount' => 100000,
        'bank_name' => 'VCB',
        'account_number' => '1234567890',
        'account_name' => 'Test User',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ Đã tạo withdraw request test với ID: $withdrawId\n";
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}

echo "\n=== Test hoàn thành ===\n";