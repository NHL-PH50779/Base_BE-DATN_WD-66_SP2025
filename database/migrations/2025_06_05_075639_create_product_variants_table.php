<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    { 
        Schema::create('product_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->onDelete('cascade');
    $table->string('sku')->unique()->nullable(); // Mã SKU riêng cho biến thể
    $table->string('Name')->nullable(false); // Chữ hoa N, NOT NULL
$table->integer('stock')->default(0);
    $table->decimal('price', 12, 2);
    $table->integer('quantity')->default(0);
    $table->boolean('is_active')->default(true); // trạng thái biến thể có hoạt động hay không
    $table->timestamps();
    // Thêm cột deleted_at để hỗ trợ soft delete
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
