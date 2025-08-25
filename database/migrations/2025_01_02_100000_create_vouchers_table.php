<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['fixed', 'percent']); // Giảm cố định hoặc %
            $table->decimal('value', 10, 2); // Giá trị giảm
            $table->decimal('min_order_amount', 10, 2)->default(0); // Đơn hàng tối thiểu
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // Giảm tối đa
            $table->integer('quantity')->default(1); // Số lượng
            $table->integer('used_count')->default(0); // Đã sử dụng
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vouchers');
    }
};