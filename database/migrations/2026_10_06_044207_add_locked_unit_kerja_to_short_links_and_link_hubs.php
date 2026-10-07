<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom di tabel short_links
        Schema::table('short_links', function (Blueprint $table) {
            $table->foreignId('locked_unit_kerja_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('unit_kerjas')
                  ->nullOnDelete();
        });

        // Tambah kolom di tabel link_hubs
        Schema::table('link_hubs', function (Blueprint $table) {
            $table->foreignId('locked_unit_kerja_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('unit_kerjas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->dropForeign(['locked_unit_kerja_id']);
            $table->dropColumn('locked_unit_kerja_id');
        });

        Schema::table('link_hubs', function (Blueprint $table) {
            $table->dropForeign(['locked_unit_kerja_id']);
            $table->dropColumn('locked_unit_kerja_id');
        });
    }
};