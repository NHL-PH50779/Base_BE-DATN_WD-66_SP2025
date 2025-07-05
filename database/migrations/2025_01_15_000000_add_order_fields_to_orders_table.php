<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('pending');
            $table->string('coupon_code')->nullable();
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->string('vnpay_txn_ref')->nullable();
            $table->string('vnpay_response_code')->nullable();
            $table->string('vnpay_transaction_no')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'phone', 'email', 'address', 'note', 
                'payment_method', 'payment_status', 'coupon_code', 
                'coupon_discount', 'vnpay_txn_ref', 'vnpay_response_code', 
                'vnpay_transaction_no', 'paid_at'
            ]);
        });
    }
};