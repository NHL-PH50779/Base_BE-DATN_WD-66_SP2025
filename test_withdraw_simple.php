<?php

// Test script đơn giản để kiểm tra API
$baseUrl = 'http://localhost:8000/api';

echo "=== Test Withdraw Table ===\n";
$response = file_get_contents($baseUrl . '/test/withdraw-table');
echo "Response: " . $response . "\n\n";

echo "=== Test hoàn thành ===\n";