<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            // Thêm các trường VNPay nếu chưa có
            if (!Schema::hasColumn('payments', 'vnp_txn_ref')) {
                $table->string('vnp_txn_ref')->nullable()->after('order_id');
            }
            if (!Schema::hasColumn('payments', 'vnp_response_code')) {
                $table->string('vnp_response_code')->nullable()->after('vnp_txn_ref');
            }
            if (!Schema::hasColumn('payments', 'vnp_transaction_no')) {
                $table->string('vnp_transaction_no')->nullable()->after('vnp_response_code');
            }
            if (!Schema::hasColumn('payments', 'vnp_bank_code')) {
                $table->string('vnp_bank_code')->nullable()->after('vnp_transaction_no');
            }
            if (!Schema::hasColumn('payments', 'vnp_pay_date')) {
                $table->string('vnp_pay_date')->nullable()->after('vnp_bank_code');
            }
            if (!Schema::hasColumn('payments', 'vnp_order_info')) {
                $table->string('vnp_order_info')->nullable()->after('vnp_pay_date');
            }
            if (!Schema::hasColumn('payments', 'vnp_data')) {
                $table->json('vnp_data')->nullable()->after('response_data');
            }
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = [
                'vnp_txn_ref', 'vnp_response_code', 'vnp_transaction_no', 
                'vnp_bank_code', 'vnp_pay_date', 'vnp_order_info', 'vnp_data'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};