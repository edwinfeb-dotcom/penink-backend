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
        Schema::create('link_hub_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('link_hub_id')
                ->constrained('link_hubs')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('url');

            $table->integer('sort_order')->default(0);

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_hub_items');
    }
};