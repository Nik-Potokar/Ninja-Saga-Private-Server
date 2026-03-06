<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores per-character event progress for all in-game events.
     *
     * One row per (character, event_key) pair. event_key is a stable
     * snake_case identifier, e.g. 'monster_hunter', 'dragon_hunt',
     * 'justice_badge', 'confronting_death_2025', 'thanksgiving_2025'.
     */
    public function up(): void
    {
        Schema::create('character_event_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->string('event_key', 100);

            // Energy / attempts remaining (Monster Hunter = 0-100, CD/TG = 0-8/10).
            $table->unsignedSmallInteger('energy')->default(0);

            // Total battles completed this event cycle.
            $table->unsignedInteger('battles')->default(0);

            // JSON array of milestone indices already claimed, e.g. [0, 2].
            $table->json('milestones_claimed')->nullable();

            // One-shot purchase flag (ThanksGiving package, etc.).
            $table->boolean('bought')->default(false);

            // Catch-all for event-specific data (dragon-ball counts, etc.).
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->unique(['character_id', 'event_key'], 'ced_char_event_unique');
            $table->foreign('character_id')->references('id')->on('characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_event_data');
    }
};
