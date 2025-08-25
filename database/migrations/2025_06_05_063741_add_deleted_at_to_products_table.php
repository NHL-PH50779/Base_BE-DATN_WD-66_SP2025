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
    // Skip - products table already has softDeletes in create migration
    return;
}

public function down()
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropSoftDeletes();
    });
}


};
