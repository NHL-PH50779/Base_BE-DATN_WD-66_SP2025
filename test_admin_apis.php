<?php

// Test các API admin để đảm bảo hoạt động tốt

$baseUrl = 'http://localhost:8000/api';

function testApi($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'response' => $response
    ];
}

echo "=== TESTING ADMIN APIs ===\n\n";

// Test 1: Admin notifications
echo "1. Testing /admin/notifications\n";
$result = testApi($baseUrl . '/admin/notifications');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] == 200) {
    $data = json_decode($result['response'], true);
    echo "✅ Success - Found " . count($data['data']) . " notifications\n";
} else {
    echo "❌ Failed\n";
}
echo "\n";

// Test 2: Admin notifications unread count
echo "2. Testing /admin/notifications/unread-count\n";
$result = testApi($baseUrl . '/admin/notifications/unread-count');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] == 200) {
    $data = json_decode($result['response'], true);
    echo "✅ Success - Unread count: " . $data['count'] . "\n";
} else {
    echo "❌ Failed\n";
}
echo "\n";

// Test 3: Admin comments
echo "3. Testing /admin/comments\n";
$result = testApi($baseUrl . '/admin/comments');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] == 200) {
    $data = json_decode($result['response'], true);
    echo "✅ Success - Found " . count($data['data']) . " comments\n";
} else {
    echo "❌ Failed\n";
}
echo "\n";

// Test 4: Dashboard stats
echo "4. Testing /admin/dashboard/stats\n";
$result = testApi($baseUrl . '/admin/dashboard/stats');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] == 200) {
    $data = json_decode($result['response'], true);
    echo "✅ Success - Stats loaded\n";
    echo "   - Orders: " . $data['data']['totals']['orders'] . "\n";
    echo "   - Products: " . $data['data']['totals']['products'] . "\n";
} else {
    echo "❌ Failed\n";
}
echo "\n";

// Test 5: Admin orders
echo "5. Testing /admin/orders\n";
$result = testApi($baseUrl . '/admin/orders');
echo "Status: " . $result['status'] . "\n";
if ($result['status'] == 200) {
    $data = json_decode($result['response'], true);
    echo "✅ Success - Found " . count($data['data']) . " orders\n";
} else {
    echo "❌ Failed\n";
}
echo "\n";

// Test 6: Update order status (nếu có đơn hàng)
echo "6. Testing order status update\n";
$ordersResult = testApi($baseUrl . '/admin/orders');
if ($ordersResult['status'] == 200) {
    $ordersData = json_decode($ordersResult['response'], true);
    if (!empty($ordersData['data'])) {
        $firstOrder = $ordersData['data'][0];
        $orderId = $firstOrder['id'];
        
        echo "Testing update order #{$orderId} status\n";
        $updateResult = testApi(
            $baseUrl . "/admin/orders/{$orderId}/order-status", 
            'PUT', 
            ['order_status_id' => 2]
        );
        echo "Status: " . $updateResult['status'] . "\n";
        if ($updateResult['status'] == 200) {
            echo "✅ Success - Order status updated\n";
        } else {
            echo "❌ Failed - " . $updateResult['response'] . "\n";
        }
    } else {
        echo "No orders found to test update\n";
    }
} else {
    echo "Cannot test order update - no orders available\n";
}

echo "\n=== TEST COMPLETED ===\n";