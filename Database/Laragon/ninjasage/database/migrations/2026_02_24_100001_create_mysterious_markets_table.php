<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mysterious_markets', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false)->index();
            $table->timestamp('ends_at');
            $table->string('discount', 10)->default('0'); // Emblem discount % shown as string
            $table->unsignedInteger('refresh_cost')->default(50);
            $table->unsignedInteger('refresh_max')->default(3);
            // Up to 4 items shown in the store: [{code: "skill_x", prices: [500, 800]}, ...]
            $table->json('items')->nullable();
            // All available packages for the full list + refresh pool: [{code: "skill_x", prices: [500, 800]}, ...]
            $table->json('all_packages')->nullable();
            $table->timestamps();
        });

        Schema::create('character_mysterious_markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->foreignId('market_id')->constrained('mysterious_markets')->onDelete('cascade');
            // Remaining refreshes for this character
            $table->unsignedInteger('refresh_count')->default(0);
            // If null, use market's default items; after refresh, store custom selection
            $table->json('custom_items')->nullable();
            $table->timestamps();
            $table->unique(['character_id', 'market_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_mysterious_markets');
        Schema::dropIfExists('mysterious_markets');
    }
};
