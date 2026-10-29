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
    Schema::table('users', function (Blueprint $table) {
        $table->string('otp_code', 6)->nullable()->after('password');
        $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        $table->boolean('is_verified')->default(false)->after('otp_expires_at');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['otp_code', 'otp_expires_at', 'is_verified']);
    });
}
};
