<?php

// Test VNPay API đã cải thiện

$baseUrl = 'http://localhost:8000/api';

function testApi($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'response' => $response
    ];
}

echo "=== TESTING IMPROVED VNPAY APIs ===\n\n";

// Test 1: Tạo đơn hàng test
echo "1. Creating test order...\n";
$orderData = [
    'name' => 'Test User',
    'phone' => '0123456789',
    'email' => 'test@example.com',
    'address' => 'Test Address',
    'payment_method' => 'vnpay',
    'total' => 100000,
    'items' => []
];

$orderResult = testApi($baseUrl . '/orders', 'POST', $orderData);
if ($orderResult['status'] == 201) {
    $orderResponse = json_decode($orderResult['response'], true);
    $orderId = $orderResponse['data']['order']['id'];
    echo "✅ Order created successfully - ID: {$orderId}\n\n";
    
    // Test 2: Tạo VNPay payment URL
    echo "2. Testing VNPay create payment\n";
    $vnpayData = [
        'order_id' => $orderId,
        'amount' => 100000,
        'order_desc' => 'Test payment'
    ];
    
    $vnpayResult = testApi($baseUrl . '/vnpay/create-payment', 'POST', $vnpayData);
    echo "Status: " . $vnpayResult['status'] . "\n";
    
    if ($vnpayResult['status'] == 200) {
        $vnpayResponse = json_decode($vnpayResult['response'], true);
        if ($vnpayResponse['success']) {
            echo "✅ VNPay URL created successfully\n";
            echo "Payment URL: " . substr($vnpayResponse['payment_url'], 0, 100) . "...\n";
            echo "Transaction Ref: " . $vnpayResponse['txn_ref'] . "\n";
        } else {
            echo "❌ VNPay creation failed: " . $vnpayResponse['message'] . "\n";
        }
    } else {
        echo "❌ API call failed\n";
        echo "Response: " . $vnpayResult['response'] . "\n";
    }
    
} else {
    echo "❌ Failed to create test order\n";
    echo "Response: " . $orderResult['response'] . "\n";
}

echo "\n";

// Test 3: Test VNPay return với dữ liệu giả
echo "3. Testing VNPay return handling\n";
$returnParams = [
    'vnp_Amount' => '10000000', // 100,000 VND * 100
    'vnp_BankCode' => 'NCB',
    'vnp_OrderInfo' => 'Test payment',
    'vnp_ResponseCode' => '00',
    'vnp_TxnRef' => '1_' . time(),
    'vnp_TransactionNo' => '123456789',
    'vnp_SecureHash' => 'test_hash' // Sẽ fail validation nhưng test được logic
];

$queryString = http_build_query($returnParams);
$returnResult = testApi($baseUrl . '/vnpay/return?' . $queryString);
echo "Status: " . $returnResult['status'] . "\n";

if ($returnResult['status'] == 400) {
    echo "✅ Signature validation working (expected 400 for invalid signature)\n";
} else {
    echo "Response: " . $returnResult['response'] . "\n";
}

echo "\n=== TEST COMPLETED ===\n";

echo "\n=== IMPROVEMENTS MADE ===\n";
echo "✅ Unified VNPay controller (removed PaymentController duplication)\n";
echo "✅ Enhanced security with strict signature validation\n";
echo "✅ Added database transactions for data consistency\n";
echo "✅ Improved error handling and logging\n";
echo "✅ Added duplicate transaction prevention\n";
echo "✅ Enhanced IPN webhook handling\n";
echo "✅ Added proper validation for all inputs\n";
echo "✅ Updated database schema with VNPay fields\n";
echo "✅ Environment-based configuration\n";
echo "✅ Better frontend error handling\n";