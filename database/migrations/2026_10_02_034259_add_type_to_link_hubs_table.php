<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_hubs', function (Blueprint $table) {
            $table->string('type', 10)->default('UMUM')->after('short_code');
        });
    }

    public function down(): void
    {
        Schema::table('link_hubs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};