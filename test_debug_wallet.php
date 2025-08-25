<?php

$baseUrl = 'http://localhost:8000/api';
$email = 'phamdiemle3110@gmail.com';

echo "=== Test Debug Wallet API ===\n";

$response = file_get_contents($baseUrl . '/debug/wallet/' . urlencode($email));
echo "Response: " . $response . "\n\n";

echo "=== Kết luận ===\n";
$data = json_decode($response, true);
if ($data && isset($data['wallet']['balance'])) {
    echo "✅ API trả về balance: " . $data['wallet']['formatted_balance'] . "\n";
    echo "✅ Nếu frontend vẫn hiển thị 450,000 VND thì vấn đề là:\n";
    echo "   1. Cache browser - Ctrl+F5 để hard refresh\n";
    echo "   2. Token authentication không đúng\n";
    echo "   3. API endpoint sai\n";
} else {
    echo "❌ API có lỗi\n";
}