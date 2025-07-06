<?php

// Test API sản phẩm
$url = 'http://127.0.0.1:8000/api/products';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "Products count: " . count($data['data']) . "\n";
    if (!empty($data['data'])) {
        echo "First product: " . $data['data'][0]['name'] . "\n";
    }
} else {
    echo "API Error!\n";
}