<?php

echo "📧 TESTING ORDER EMAIL SYSTEM\n";
echo str_repeat('=', 50) . "\n";

// Test update order status (should send email)
echo "Testing Order Status Update Email...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/admin/orders/107/order-status');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['order_status_id' => 2]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: " . ($httpCode == 200 ? "✅ SUCCESS" : "❌ FAILED ($httpCode)") . "\n";
echo "Response: " . $response . "\n\n";

echo "📬 Check email inbox for order status notification!\n";