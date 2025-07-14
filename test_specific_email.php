<?php

echo "🔍 TESTING SPECIFIC EMAIL\n";
echo str_repeat('=', 50) . "\n";

$email = 'tuandzdz123456789@gmail.com';
$otp = '470199';

// Test reset password với email và OTP cụ thể
echo "Testing reset password for: $email\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/reset-password');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'otp' => $otp,
    'password' => '123123',
    'password_confirmation' => '123123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $httpCode\n";
echo "   Response: " . $response . "\n\n";

echo "✅ Test completed!\n";