<?php

echo "🔍 DEBUG RESET PASSWORD VALIDATION\n";
echo str_repeat('=', 50) . "\n";

// Test reset password with all fields
echo "Testing Reset Password with all required fields...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/reset-password');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'test234@gmail.com', // Email tồn tại trong DB
    'otp' => '123456',
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123'
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

echo "✅ Reset password debug completed!\n";