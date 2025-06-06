<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('category_id');
            $table->string('thumbnail', 255)->nullable();

            // created_at và updated_at theo kiểu thủ công
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            // Khóa ngoại
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict')->onUpdate('cascade');
            $table->softDeletes(); 
            $table->boolean('is_active')->default(true);
            
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};
