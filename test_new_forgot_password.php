<?php

echo "🔐 TESTING NEW FORGOT PASSWORD SYSTEM\n";
echo str_repeat('=', 50) . "\n";

$email = 'test234@gmail.com';

// Step 1: Send OTP for forgot password
echo "Step 1: Sending OTP for forgot password to $email...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/forgot-password');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email]));
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

if ($httpCode == 200) {
    echo "✅ OTP sent successfully! Check your email.\n";
} else {
    echo "❌ Failed to send OTP!\n";
}

echo "\n" . str_repeat('=', 50) . "\n";