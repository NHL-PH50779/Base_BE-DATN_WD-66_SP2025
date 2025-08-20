<?php

// Test API wallet
$baseUrl = 'http://localhost:8000/api';

// Tạo token test
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

try {
    // Lấy user test
    $testUser = $capsule->table('users')->where('email', 'admin@admin.com')->first();
    if (!$testUser) {
        echo "❌ Không tìm thấy user test\n";
        return;
    }
    
    // Tạo token test
    $token = base64_encode($testUser->id . '|test_token');
    
    // Test API wallet
    echo "=== Test Wallet API ===\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]
    ]);
    
    $response = @file_get_contents($baseUrl . '/wallet', false, $context);
    if ($response === false) {
        echo "❌ Lỗi khi gọi API wallet\n";
        $error = error_get_last();
        echo "Error: " . $error['message'] . "\n";
    } else {
        echo "✅ Response từ API wallet:\n";
        $data = json_decode($response, true);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    // Kiểm tra wallet trong database
    echo "\n=== Wallet trong Database ===\n";
    $wallet = $capsule->table('wallets')->where('user_id', $testUser->id)->first();
    if ($wallet) {
        echo "✅ Wallet tồn tại:\n";
        echo "- User ID: {$wallet->user_id}\n";
        echo "- Balance: " . number_format($wallet->balance) . " VND\n";
        echo "- Created: {$wallet->created_at}\n";
        echo "- Updated: {$wallet->updated_at}\n";
    } else {
        echo "❌ Không tìm thấy wallet trong database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}