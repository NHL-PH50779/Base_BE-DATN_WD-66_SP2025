<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Products
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'brand_id') &&
                Schema::hasColumn('products', 'category_id') &&
                Schema::hasColumn('products', 'is_active')) {
                $table->index(['brand_id', 'category_id', 'is_active'], 'idx_products_brand_cat_active');
            }

            if (Schema::hasColumn('products', 'is_active') &&
                Schema::hasColumn('products', 'created_at')) {
                $table->index(['is_active', 'created_at'], 'idx_products_active_created');
            }
        });

        // Product variants
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'product_id') &&
                Schema::hasColumn('product_variants', 'is_active') &&
                Schema::hasColumn('product_variants', 'price')) {
                $table->index(['product_id', 'is_active', 'price'], 'idx_variants_product_active_price');
            }
        });

        // News
        Schema::table('news', function (Blueprint $table) {
            if (Schema::hasColumn('news', 'published_at')) {
                $table->index(['published_at'], 'idx_news_published_at');
            }
        });

        // Orders
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'user_id') &&
                Schema::hasColumn('orders', 'order_status_id')) {
                $table->index(['user_id', 'order_status_id'], 'idx_orders_user_status');
            }

            if (Schema::hasColumn('orders', 'payment_method') &&
                Schema::hasColumn('orders', 'payment_status_id')) {
                $table->index(['payment_method', 'payment_status_id'], 'idx_orders_payment_method_status');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_brand_cat_active');
            $table->dropIndex('idx_products_active_created');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_variants_product_active_price');
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex('idx_news_published_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_user_status');
            $table->dropIndex('idx_orders_payment_method_status');
        });
    }
};
