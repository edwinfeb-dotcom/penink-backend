<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // User yang melakukan aktivitas
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Jenis aktivitas
            $table->string('action');

            // Objek yang terkena aktivitas
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            // Detail tambahan
            $table->text('description')->nullable();

            // Data tambahan dalam format JSON
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index untuk pencarian audit
            $table->index(['target_type', 'target_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};