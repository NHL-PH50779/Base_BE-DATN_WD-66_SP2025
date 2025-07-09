<?php

// Test login để lấy token
$url = 'http://localhost:8000/api/login';

$loginData = [
    'email' => 'test234@gmail.com', // Email của user ID 16
    'password' => '123456' // Password vừa đặt
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Login Test ===\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    $token = $data['token'] ?? null;
    
    if ($token) {
        echo "\n✅ Login successful!\n";
        echo "Token: $token\n";
        
        // Test wallet API với token
        echo "\n=== Test Wallet API với Token ===\n";
        
        $walletUrl = 'http://localhost:8000/api/wallet';
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $walletUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $walletResponse = curl_exec($ch2);
        $walletHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        echo "Wallet HTTP Code: $walletHttpCode\n";
        echo "Wallet Response: $walletResponse\n";
        
        if ($walletHttpCode == 200) {
            echo "✅ Wallet API successful with token!\n";
        } else {
            echo "❌ Wallet API failed with token!\n";
        }
    }
} else {
    echo "❌ Login failed!\n";
}