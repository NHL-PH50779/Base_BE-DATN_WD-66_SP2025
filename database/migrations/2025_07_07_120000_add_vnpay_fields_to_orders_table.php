<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'vnpay_txn_ref')) {
                $table->string('vnpay_txn_ref')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'vnpay_transaction_no')) {
                $table->string('vnpay_transaction_no')->nullable()->after('vnpay_txn_ref');
            }
            if (!Schema::hasColumn('orders', 'vnpay_response_code')) {
                $table->string('vnpay_response_code')->nullable()->after('vnpay_transaction_no');
            }
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('vnpay_response_code');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = ['vnpay_txn_ref', 'vnpay_transaction_no', 'vnpay_response_code', 'paid_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};