<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('remember_token');
            $table->string('email_otp')->nullable()->after('is_verified');
            $table->timestamp('otp_expires_at')->nullable()->after('email_otp');
            $table->timestamp('last_otp_sent_at')->nullable()->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_verified',
                'email_otp',
                'otp_expires_at',
                'last_otp_sent_at',
            ]);
        });
    }
};
