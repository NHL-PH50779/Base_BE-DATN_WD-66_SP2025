<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('orders', function (Blueprint $table) {
        if (!Schema::hasColumn('orders', 'user_id')) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        }
        if (!Schema::hasColumn('orders', 'status')) {
            $table->string('status')->default('pending')->after('user_id');
        }
        if (!Schema::hasColumn('orders', 'total_price')) {
            $table->decimal('total_price', 15, 2)->default(0)->after('status');
        }
    });
}

public function down()
{
    Schema::table('orders', function (Blueprint $table) {
        if (Schema::hasColumn('orders', 'user_id')) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        }
        if (Schema::hasColumn('orders', 'status')) {
            $table->dropColumn('status');
        }
        if (Schema::hasColumn('orders', 'total_price')) {
            $table->dropColumn('total_price');
        }
    });
}

};
