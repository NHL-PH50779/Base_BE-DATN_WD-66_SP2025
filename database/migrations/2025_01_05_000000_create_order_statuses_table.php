<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Tạo bảng trạng thái đơn hàng nếu chưa có
        if (!Schema::hasTable('order_statuses')) {
            Schema::create('order_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();
            });

            // Thêm dữ liệu mặc định
            DB::table('order_statuses')->insert([
                ['id' => 1, 'name' => 'Chờ xác nhận', 'description' => 'Đơn hàng đang chờ xác nhận'],
                ['id' => 2, 'name' => 'Đã xác nhận', 'description' => 'Đơn hàng đã được xác nhận và có thể hủy'],
                ['id' => 3, 'name' => 'Đang chuẩn bị', 'description' => 'Đang chuẩn bị hàng'],
                ['id' => 4, 'name' => 'Đang giao', 'description' => 'Đang giao hàng'],
                ['id' => 5, 'name' => 'Đã giao', 'description' => 'Đã giao hàng thành công'],
                ['id' => 6, 'name' => 'Đã hủy', 'description' => 'Đơn hàng đã bị hủy'],
            ]);
        }

        // Tạo bảng trạng thái thanh toán nếu chưa có
        if (!Schema::hasTable('payment_statuses')) {
            Schema::create('payment_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamps();
            });

            // Thêm dữ liệu mặc định
            DB::table('payment_statuses')->insert([
                ['id' => 1, 'name' => 'Chưa thanh toán', 'description' => 'Chưa thanh toán'],
                ['id' => 2, 'name' => 'Đã thanh toán', 'description' => 'Đã thanh toán thành công'],
                ['id' => 3, 'name' => 'Đã hoàn tiền', 'description' => 'Đã hoàn tiền'],
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('payment_statuses');
        Schema::dropIfExists('order_statuses');
    }
};