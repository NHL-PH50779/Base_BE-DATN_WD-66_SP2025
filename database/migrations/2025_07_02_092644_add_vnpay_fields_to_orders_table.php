<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vnpay_txn_ref')->nullable()->after('payment_method');
            $table->string('vnpay_response_code')->nullable()->after('vnpay_txn_ref');
            $table->string('vnpay_transaction_no')->nullable()->after('vnpay_response_code');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'cancelled'])->default('pending')->after('vnpay_transaction_no');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['vnpay_txn_ref', 'vnpay_response_code', 'vnpay_transaction_no', 'payment_status', 'paid_at']);
        });
    }
};