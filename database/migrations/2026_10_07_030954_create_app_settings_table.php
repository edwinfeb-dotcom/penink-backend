<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        // Seed default values
        \DB::table('app_settings')->insert([
            [
                'key' => 'app_version',
                'value' => '1.0.0',
                'label' => 'Versi Aplikasi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_name',
                'value' => 'PENINK',
                'label' => 'Nama Aplikasi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_developer',
                'value' => 'Dinas Komunikasi dan Informatika Kabupaten Landak',
                'label' => 'Pengembang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};