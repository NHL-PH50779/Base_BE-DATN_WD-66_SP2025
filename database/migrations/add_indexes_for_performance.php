<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add indexes for better query performance
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'brand_id']);
            $table->index(['is_active', 'category_id']);
            $table->index('created_at');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'is_active']);
            $table->index('price');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'brand_id']);
            $table->dropIndex(['is_active', 'category_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_active']);
            $table->dropIndex(['price']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};