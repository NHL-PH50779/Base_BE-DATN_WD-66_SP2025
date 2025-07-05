<?php

// Test trực tiếp VNPay return endpoint
$url = 'http://127.0.0.1:8000/api/payment/vnpay/return';
$params = [
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

$fullUrl = $url . '?' . http_build_query($params);

echo "Testing URL: " . $fullUrl . "\n\n";

// Sử dụng cURL để test
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Không follow redirect để xem response
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error: " . ($error ?: 'None') . "\n";
echo "Response:\n" . $response . "\n";