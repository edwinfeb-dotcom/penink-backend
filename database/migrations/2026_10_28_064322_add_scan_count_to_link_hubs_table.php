<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_hubs', function (Blueprint $table) {
            // Cek kalau scan_count BELUM ADA, baru tambahkan
            if (!Schema::hasColumn('link_hubs', 'scan_count')) {
                $table->integer('scan_count')->default(0);
            }
            
            // Cek kalau click_count BELUM ADA, baru tambahkan
            if (!Schema::hasColumn('link_hubs', 'click_count')) {
                $table->integer('click_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('link_hubs', function (Blueprint $table) {
            // Sengaja dikosongkan agar tidak menghapus data asli saat rollback
        });
    }
};