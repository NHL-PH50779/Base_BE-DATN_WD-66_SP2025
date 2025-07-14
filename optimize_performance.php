<?php

// Performance Optimization Script
echo "🚀 OPTIMIZING PERFORMANCE...\n";
echo str_repeat('=', 50) . "\n";

// Clear all caches
echo "1. Clearing caches...\n";
exec('php artisan cache:clear');
exec('php artisan config:clear');
exec('php artisan route:clear');
exec('php artisan view:clear');

// Optimize for production
echo "2. Optimizing for production...\n";
exec('php artisan config:cache');
exec('php artisan route:cache');
exec('php artisan view:cache');

// Warm up cache with popular endpoints
echo "3. Warming up cache...\n";

$endpoints = [
    '/api/products',
    '/api/categories', 
    '/api/brands',
    '/api/news',
    '/api/vouchers/available'
];

foreach ($endpoints as $endpoint) {
    $url = 'http://127.0.0.1:8000' . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
    echo "   Warmed up: $endpoint\n";
}

echo "\n✅ Performance optimization completed!\n";
echo "🔥 Your API should now be faster!\n";