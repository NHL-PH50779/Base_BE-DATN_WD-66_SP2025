<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['brand_id', 'category_id', 'is_active']);
            $table->index(['is_active', 'created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'is_active', 'price']);
        });

        Schema::table('news', function (Blueprint $table) {
            $table->index(['published_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'order_status_id']);
            $table->index(['payment_method', 'payment_status_id']);
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'category_id', 'is_active']);
            $table->dropIndex(['is_active', 'created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_active', 'price']);
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'order_status_id']);
            $table->dropIndex(['payment_method', 'payment_status_id']);
        });
    }
};