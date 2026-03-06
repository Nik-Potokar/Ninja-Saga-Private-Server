<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds optional scheduling columns to game_events.
     *
     * starts_at – when set, the sync command will flip active = true at this time.
     * ends_at   – when set, the sync command will flip active = false at this time.
     *
     * Both columns are nullable; leaving them null means the event is managed manually.
     */
    public function up(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('sort_order');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
