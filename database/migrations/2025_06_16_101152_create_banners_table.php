<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id(); // id tự tăng
            $table->string('image'); // ảnh banner
            $table->string('link')->nullable(); // link khi click
            $table->integer('position')->default(0); // vị trí hiển thị
            $table->boolean('status')->default(true); // trạng thái: hiển thị hay không
            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
