<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Khởi tạo database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'datn_sp2025_p',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    // Kiểm tra cấu trúc bảng
    echo "=== Cấu trúc bảng products ===\n";
    $columns = DB::select('DESCRIBE products');
    foreach ($columns as $column) {
        echo "{$column->Field} - {$column->Type} - {$column->Null} - {$column->Default}\n";
    }
    
    echo "\n=== Kiểm tra soft delete ===\n";
    
    // Lấy sản phẩm đầu tiên
    $product = DB::table('products')->first();
    if ($product) {
        echo "Sản phẩm test: ID {$product->id} - {$product->name}\n";
        
        // Kiểm tra có cột deleted_at không
        if (property_exists($product, 'deleted_at')) {
            echo "✓ Có cột deleted_at: {$product->deleted_at}\n";
        } else {
            echo "✗ KHÔNG có cột deleted_at\n";
        }
    }
    
    // Đếm sản phẩm
    $total = DB::table('products')->count();
    $deleted = DB::table('products')->whereNotNull('deleted_at')->count();
    
    echo "\nTổng sản phẩm: {$total}\n";
    echo "Sản phẩm đã xóa: {$deleted}\n";
    
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}