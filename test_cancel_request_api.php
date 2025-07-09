<?php

// Test API cancel request
$url = 'http://localhost:8000/api/orders/103/cancel-request';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Cancel Request API ===\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode == 200) {
    echo "✅ Cancel request successful!\n";
    
    // Bây giờ test approve cancel
    echo "\n=== Approve Cancel API ===\n";
    
    $url2 = 'http://localhost:8000/api/admin/orders/103/approve-cancel';
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $url2);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    echo "HTTP Code: $httpCode2\n";
    echo "Response: $response2\n";
    
    if ($httpCode2 == 200) {
        echo "✅ Approve cancel successful!\n";
    } else {
        echo "❌ Approve cancel failed!\n";
    }
} else {
    echo "❌ Cancel request failed!\n";
}