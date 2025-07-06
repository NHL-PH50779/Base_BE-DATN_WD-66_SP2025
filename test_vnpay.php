<?php
// Test VNPay API
echo "Testing VNPay API...\n";

// Test 1: Create Payment
$createPaymentData = [
    'order_id' => 123,
    'amount' => 100000,
    'order_desc' => 'Test payment'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/vnpay/create-payment');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createPaymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Create Payment Response (HTTP $httpCode):\n";
echo $response . "\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data['success']) {
        echo "✅ VNPay URL created successfully!\n";
        echo "Payment URL: " . $data['payment_url'] . "\n";
    }
} else {
    echo "❌ Failed to create VNPay URL\n";
}
?>