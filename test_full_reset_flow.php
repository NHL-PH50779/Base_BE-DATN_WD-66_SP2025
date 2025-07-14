<?php

echo "🔄 TESTING FULL RESET PASSWORD FLOW\n";
echo str_repeat('=', 50) . "\n";

$email = 'test234@gmail.com';

// Step 1: Send OTP
echo "Step 1: Sending OTP to $email...\n";
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
echo "   Response: " . $response . "\n";

if ($httpCode == 200) {
    echo "\n✅ OTP sent successfully!\n";
    echo "📧 Please check your email and enter the OTP below:\n";
    echo "OTP: ";
    $otp = trim(fgets(STDIN));
    
    // Step 2: Reset password with real OTP
    echo "\nStep 2: Resetting password with OTP: $otp...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/reset-password');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'otp' => $otp,
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
    echo "   Response: " . $response . "\n";
    
    if ($httpCode == 200) {
        echo "\n🎉 Password reset successful!\n";
    } else {
        echo "\n❌ Password reset failed!\n";
    }
} else {
    echo "\n❌ Failed to send OTP!\n";
}

echo "\n" . str_repeat('=', 50) . "\n";