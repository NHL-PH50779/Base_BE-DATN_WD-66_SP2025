<?php

// Test API wallet
$url = 'http://localhost:8000/api/wallet';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Wallet API Test ===\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode == 200) {
    echo "✅ Wallet API successful!\n";
    
    // Test transactions API
    echo "\n=== Wallet Transactions API Test ===\n";
    
    $url2 = 'http://localhost:8000/api/wallet/transactions';
    
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $url2);
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
        echo "✅ Transactions API successful!\n";
    } else {
        echo "❌ Transactions API failed!\n";
    }
} else {
    echo "❌ Wallet API failed!\n";
}