<?php
// Debug API để kiểm tra dữ liệu null
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Kết nối database
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=datn_wd66', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Kiểm tra cart items có thumbnail null
    $stmt = $pdo->query("
        SELECT ci.id, ci.product_id, p.name, p.thumbnail, pv.Name as variant_name
        FROM cart_items ci 
        LEFT JOIN products p ON ci.product_id = p.id 
        LEFT JOIN product_variants pv ON ci.product_variant_id = pv.id
        WHERE p.thumbnail IS NULL OR p.thumbnail = ''
        LIMIT 10
    ");
    $cartIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kiểm tra order items có dữ liệu null
    $stmt = $pdo->query("
        SELECT oi.id, oi.product_id, oi.product_name, oi.product_image, oi.variant_name
        FROM order_items oi 
        WHERE oi.product_image IS NULL OR oi.variant_name IS NULL
        LIMIT 10
    ");
    $orderIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'cart_issues' => $cartIssues,
        'order_issues' => $orderIssues,
        'message' => 'Debug data checked'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>