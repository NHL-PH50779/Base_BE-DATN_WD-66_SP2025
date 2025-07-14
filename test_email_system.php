<?php

echo "🔥 TESTING EMAIL SYSTEM\n";
echo str_repeat('=', 50) . "\n";

// Test 1: Send OTP
echo "1. Testing Send OTP...\n";
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

// Test 2: Verify OTP (will fail without real OTP)
echo "2. Testing Verify OTP (demo)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/verify-otp');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'tuanpaph50818@gmail.com',
    'otp' => '123456'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: " . ($httpCode == 422 ? "✅ EXPECTED FAIL" : "❌ UNEXPECTED ($httpCode)") . "\n";
echo "   Response: " . $response . "\n\n";

echo "📧 Email system is ready!\n";
echo "📝 Next steps:\n";
echo "   1. Check your email for OTP\n";
echo "   2. Test order status email from admin panel\n";
echo "   3. Integrate with frontend\n";