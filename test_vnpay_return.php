<?php

// Test script để kiểm tra VNPay Return
require_once 'vendor/autoload.php';

// Simulate VNPay return parameters (successful payment)
$returnParams = [
    'vnp_Amount' => '5000000',
    'vnp_BankCode' => 'NCB',
    'vnp_BankTranNo' => 'VNP14692927',
    'vnp_CardType' => 'ATM',
    'vnp_OrderInfo' => 'Test thanh toan VNPay',
    'vnp_PayDate' => '20250704104600',
    'vnp_ResponseCode' => '00',
    'vnp_TmnCode' => 'E53K6FXV',
    'vnp_TransactionNo' => '14692927',
    'vnp_TransactionStatus' => '00',
    'vnp_TxnRef' => '38',
    'vnp_SecureHash' => '' // Will be calculated
];

// Calculate secure hash
$vnp_HashSecret = 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB';
ksort($returnParams);
$hashData = "";
$i = 0;

foreach ($returnParams as $key => $value) {
    if ($key !== 'vnp_SecureHash') {
        if ($i == 1) {
            $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }
}

$returnParams['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $vnp_HashSecret);

echo "=== TEST VNPAY RETURN ===\n";
echo "Return params: " . json_encode($returnParams, JSON_PRETTY_PRINT) . "\n\n";

// Test return URL
$returnUrl = 'http://127.0.0.1:8000/api/payment/vnpay/return?' . http_build_query($returnParams);
echo "Return URL: $returnUrl\n\n";

// Test with cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $returnUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($redirectUrl) {
    echo "Redirect URL: $redirectUrl\n";
    
    // Parse redirect URL to check status
    $urlParts = parse_url($redirectUrl);
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $queryParams);
        if (isset($queryParams['vnp_ResponseCode']) && $queryParams['vnp_ResponseCode'] === '00') {
            echo "✅ SUCCESS: Payment processed successfully\n";
            echo "Order ID: " . ($queryParams['vnp_TxnRef'] ?? 'N/A') . "\n";
        } else {
            echo "❌ ERROR: Payment failed\n";
            echo "Response Code: " . ($queryParams['vnp_ResponseCode'] ?? 'N/A') . "\n";
        }
    }
} else {
    echo "Response: $response\n";
}

echo "\n=== END TEST ===\n";