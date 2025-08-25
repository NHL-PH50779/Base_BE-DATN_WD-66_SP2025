<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'deleted_at']);
            $table->index(['brand_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index('created_at');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'is_active']);
            $table->index('stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index('order_status_id');
            $table->index('payment_status_id');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'deleted_at']);
            $table->dropIndex(['brand_id', 'is_active']);
            $table->dropIndex(['category_id', 'is_active']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_active']);
            $table->dropIndex(['stock']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['order_status_id']);
            $table->dropIndex(['payment_status_id']);
        });
    }
};