<?php

// Test script để kiểm tra VNPay API
require_once 'vendor/autoload.php';

use Illuminate\Http\Request;

// Test data
$testData = [
    'order_id' => 38,
    'amount' => 50000,
    'order_desc' => 'Test thanh toán VNPay',
    'bank_code' => ''
];

echo "=== TEST VNPAY API ===\n";
echo "Test data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Test URL
$baseUrl = 'http://127.0.0.1:8000/api';
$testUrl = $baseUrl . '/payment/vnpay';

echo "Testing URL: $testUrl\n";
echo "Method: POST\n\n";

// Sử dụng cURL để test
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['payment_url'])) {
        echo "✅ SUCCESS: Payment URL generated\n";
        echo "Payment URL: " . $data['payment_url'] . "\n";
    } else {
        echo "❌ ERROR: No payment URL in response\n";
    }
} else {
    echo "❌ ERROR: HTTP $httpCode\n";
    $errorData = json_decode($response, true);
    if ($errorData) {
        echo "Error details: " . json_encode($errorData, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n=== END TEST ===\n";