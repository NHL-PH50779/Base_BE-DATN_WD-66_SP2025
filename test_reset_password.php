<?php

echo "🔐 TESTING RESET PASSWORD SYSTEM\n";
echo str_repeat('=', 50) . "\n";

// Test 1: Send OTP for password reset
echo "1. Testing Send OTP for Reset Password...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/send-otp');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'tuanpaph50818@gmail.com']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: " . ($httpCode == 200 ? "✅ SUCCESS" : "❌ FAILED ($httpCode)") . "\n";
echo "   Response: " . $response . "\n\n";

// Test 2: Reset Password (will fail without real OTP)
echo "2. Testing Reset Password (demo with fake OTP)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/reset-password');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'tuanpaph50818@gmail.com',
    'otp' => '123456',
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: " . ($httpCode == 422 ? "✅ EXPECTED FAIL" : "❌ UNEXPECTED ($httpCode)") . "\n";
echo "   Response: " . $response . "\n\n";

echo "🔑 Reset Password system is ready!\n";
echo "📝 Flow:\n";
echo "   1. User enters email → Send OTP\n";
echo "   2. User enters OTP → Verify\n";
echo "   3. User enters new password → Reset\n";