<?php

// Test VNPay Return callback
$returnData = [
    'vnp_Amount' => '5000000',
    'vnp_BankCode' => 'NCB',
    'vnp_BankTranNo' => 'VNP14704110',
    'vnp_CardType' => 'ATM',
    'vnp_OrderInfo' => 'Test thanh toan VNPay',
    'vnp_PayDate' => '20250704110900',
    'vnp_ResponseCode' => '00',
    'vnp_TmnCode' => 'E53K6FXV',
    'vnp_TransactionNo' => '14704110',
    'vnp_TransactionStatus' => '00',
    'vnp_TxnRef' => '6',
    'vnp_SecureHash' => 'test_hash' // Sẽ được tính toán thực tế
];

echo "=== TEST VNPAY RETURN ===\n";
echo "Return data: " . json_encode($returnData, JSON_PRETTY_PRINT) . "\n\n";

// Tính toán secure hash thực tế
$vnp_HashSecret = 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB';
$inputData = $returnData;
unset($inputData['vnp_SecureHash']);
ksort($inputData);

$hashData = "";
$i = 0;
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$returnData['vnp_SecureHash'] = $secureHash;

echo "Calculated hash: $secureHash\n\n";

// Test URL
$baseUrl = 'http://127.0.0.1:8000/api';
$testUrl = $baseUrl . '/payment/vnpay/return?' . http_build_query($returnData);

echo "Testing URL: $testUrl\n";
echo "Method: GET\n\n";

// Sử dụng cURL để test
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
if ($redirectUrl) {
    echo "Redirect URL: $redirectUrl\n";
}

if ($httpCode === 302 || $httpCode === 301) {
    echo "✅ SUCCESS: Redirect response received\n";
} elseif ($httpCode === 200) {
    echo "✅ SUCCESS: Direct response received\n";
} else {
    echo "❌ ERROR: HTTP $httpCode\n";
}

echo "\n=== END TEST ===\n";