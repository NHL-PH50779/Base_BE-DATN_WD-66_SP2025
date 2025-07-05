<?php

// Test VNPay return với dữ liệu thực tế
$testData = [
    'vnp_Amount' => '4420400000',
    'vnp_BankCode' => 'NCB',
    'vnp_BankTranNo' => 'VNP15056144',
    'vnp_CardType' => 'ATM',
    'vnp_OrderInfo' => 'Thanh toán đơn hàng #39',
    'vnp_PayDate' => '20250704174945',
    'vnp_ResponseCode' => '00',
    'vnp_TmnCode' => 'E53K6FXV',
    'vnp_TransactionNo' => '15056144',
    'vnp_TransactionStatus' => '00',
    'vnp_TxnRef' => '39',
    'vnp_SecureHash' => '9d6919c8d3f450115735157556a6f1f66f7e08f9ecf7cee318f2e29478687fefbb8a3b672476a850ad5d869d7e5f0f29efee8813c7f8cb5a4e4c4840f076367d'
];

// Tạo hash để verify
$vnp_HashSecret = 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB';
$inputData = $testData;
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

$calculatedHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

echo "Hash Data: " . $hashData . "\n";
echo "Calculated Hash: " . $calculatedHash . "\n";
echo "Received Hash: " . $testData['vnp_SecureHash'] . "\n";
echo "Match: " . ($calculatedHash == $testData['vnp_SecureHash'] ? 'YES' : 'NO') . "\n";

// Test URL
$queryString = http_build_query($testData);
echo "\nTest URL: http://127.0.0.1:8000/api/payment/vnpay/return?" . $queryString . "\n";