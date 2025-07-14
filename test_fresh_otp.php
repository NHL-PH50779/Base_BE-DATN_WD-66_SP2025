<?php

echo "🔄 TESTING FRESH OTP FLOW\n";
echo str_repeat('=', 50) . "\n";

$email = 'tuandzdz123456789@gmail.com';

// Step 1: Send fresh OTP
echo "Step 1: Sending fresh OTP to $email...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/send-otp');
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
    echo "✅ Fresh OTP sent! Now test reset password with the new OTP from your email.\n";
} else {
    echo "❌ Failed to send OTP!\n";
}

echo "\n" . str_repeat('=', 50) . "\n";