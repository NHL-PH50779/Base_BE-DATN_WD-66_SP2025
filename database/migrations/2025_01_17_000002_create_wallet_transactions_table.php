<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['refund', 'purchase', 'topup', 'withdraw']);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('related_order_id')->nullable();
            $table->timestamps();
            
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('related_order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallet_transactions');
    }
};