<?php

// Test script để kiểm tra API withdraw requests
$baseUrl = 'http://localhost:8000/api';

// Test 1: Kiểm tra API không cần auth
echo "=== Test 1: API Test ===\n";
$response = file_get_contents($baseUrl . '/test');
echo "Response: " . $response . "\n\n";

// Test 2: Kiểm tra API withdraw requests (không auth)
echo "=== Test 2: Withdraw Requests (No Auth) ===\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json'
    ]
]);

$response = @file_get_contents($baseUrl . '/withdraw-requests', false, $context);
if ($response === false) {
    echo "Error: Cannot access /withdraw-requests\n";
    $error = error_get_last();
    echo "Error details: " . $error['message'] . "\n";
} else {
    echo "Response: " . $response . "\n";
}
echo "\n";

// Test 3: Kiểm tra API admin withdraw requests (không auth)
echo "=== Test 3: Admin Withdraw Requests (No Auth) ===\n";
$response = @file_get_contents($baseUrl . '/admin/withdraw-requests', false, $context);
if ($response === false) {
    echo "Error: Cannot access /admin/withdraw-requests\n";
    $error = error_get_last();
    echo "Error details: " . $error['message'] . "\n";
} else {
    echo "Response: " . $response . "\n";
}
echo "\n";

echo "=== Test hoàn thành ===\n";