<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToOrderIdOnReturnRequestsTable extends Migration
{
    public function up()
    {
         if (Schema::hasTable('personal_access_tokens')) {
        return;
    }

        Schema::table('return_requests', function (Blueprint $table) {
            $table->index('order_id'); // Thêm chỉ mục cho cột order_id
        });
    }

    public function down()
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex(['order_id']); // Bỏ chỉ mục khi rollback
        });
    }
}
