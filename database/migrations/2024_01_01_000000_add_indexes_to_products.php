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
            $table->index(['created_at', 'id']);
            $table->index('name');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'deleted_at']);
            $table->dropIndex(['brand_id', 'is_active']);
            $table->dropIndex(['category_id', 'is_active']);
            $table->dropIndex(['created_at', 'id']);
            $table->dropIndex(['name']);
        });
    }
};