<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Khởi tạo database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'datn_sp2025_p',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = $capsule->schema();

try {
    // Kiểm tra xem bảng withdraw_requests đã tồn tại chưa
    if (!$schema->hasTable('withdraw_requests')) {
        echo "Tạo bảng withdraw_requests...\n";
        
        $schema->create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 15, 2);
            $table->string('bank_name', 10);
            $table->string('account_number', 20);
            $table->string('account_name', 100);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
        
        echo "✅ Đã tạo bảng withdraw_requests thành công!\n";
    } else {
        echo "✅ Bảng withdraw_requests đã tồn tại.\n";
    }
    
    // Kiểm tra các cột cần thiết
    $columns = $schema->getColumnListing('withdraw_requests');
    $requiredColumns = ['id', 'user_id', 'amount', 'bank_name', 'account_number', 'account_name', 'status', 'admin_note', 'processed_at', 'processed_by', 'created_at', 'updated_at'];
    
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (!empty($missingColumns)) {
        echo "❌ Thiếu các cột: " . implode(', ', $missingColumns) . "\n";
    } else {
        echo "✅ Tất cả các cột cần thiết đều có sẵn.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
}