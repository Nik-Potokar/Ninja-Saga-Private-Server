<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giveaways', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('prizes')->nullable();
            // [{"name": "Level 30+", "type": "level", "value": 30}, ...]
            // Supported types: level, pvp_battles, pvp_wins, rank
            $table->json('requirements')->nullable();
            $table->timestamp('ends_at');
            // Set by admin after drawing; shows winner UI in client when non-null + non-empty
            $table->json('winners')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giveaways');
    }
};