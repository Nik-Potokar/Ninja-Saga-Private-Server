<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds fields required to serve the events catalog from the database
     * instead of hardcoded arrays. Missing fields: panel, date, icon, inside, sort_order.
     * Also expands the 'type' column comment to document supported values.
     */
    public function up(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            // Which client panel/component to open for this event.
            // e.g. 'ChristmasMenu', 'MonsterHunter', 'DailyGacha'
            $table->string('panel')->nullable()->after('type');

            // Display date range shown in the catalog.
            // e.g. '25/12 - 25/03, 2026'
            // Used by seasonal and package types.
            $table->string('date')->nullable()->after('panel');

            // Icon identifier for permanent events and features.
            // e.g. 'monsterhunter', 'dragonhunt', 'leaderboard'
            $table->string('icon')->nullable()->after('date');

            // When true the client opens the panel from inside the game world
            // rather than from the main menu. Used by TailedBeast.
            $table->boolean('inside')->default(false)->after('icon');

            // Controls display order within each type group.
            $table->unsignedSmallInteger('sort_order')->default(0)->after('inside');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_events', function (Blueprint $table) {
            $table->dropColumn(['panel', 'date', 'icon', 'inside', 'sort_order']);
        });
    }
};
