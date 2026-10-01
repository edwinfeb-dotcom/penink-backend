<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('link_hubs', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('short_code')->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_hubs');
    }
};
