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
        // Missions
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('mission_id')->unique();
            $table->integer('req_lvl')->default(1);
            $table->integer('xp')->default(0);
            $table->integer('gold')->default(0);
            $table->timestamps();
        });

        // XP
        Schema::create('x_p_s', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique();
            $table->integer('character_xp'); // Renamed from xp_required
            $table->integer('pet_xp')->default(999999999);
            $table->timestamps();
        });

        // NPCs
        Schema::create('npcs', function (Blueprint $table) {
            $table->id();
            $table->string('npc_id')->unique();
            $table->string('name');
            $table->integer('level');
            $table->integer('rank');
            $table->integer('hp');
            $table->integer('cp');
            $table->integer('agility');
            $table->integer('dodge');
            $table->integer('critical');
            $table->integer('accuracy');
            $table->integer('purify');
            $table->text('description')->nullable();
            $table->json('attacks')->nullable();
            $table->integer('price_gold')->default(0);
            $table->integer('price_tokens')->default(0);
            $table->boolean('premium')->default(false);
            $table->timestamps();
        });

        // Enemies
        Schema::create('enemies', function (Blueprint $table) {
            $table->id();
            $table->string('enemy_id')->unique();
            $table->string('name');
            $table->integer('level');
            $table->integer('hp');
            $table->integer('cp');
            $table->integer('agility');
            $table->json('attacks')->nullable();
            $table->timestamps();
        });

        // Talents
        Schema::create('talents', function (Blueprint $table) {
            $table->id();
            $table->string('talent_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('skills')->nullable();
            $table->integer('price_gold')->default(0);
            $table->integer('price_tokens')->default(0);
            $table->boolean('is_emblem')->default(false);
            $table->timestamps();
        });

        // Senjutsus
        Schema::create('senjutsus', function (Blueprint $table) {
            $table->id();
            $table->string('senjutsu_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('effects')->nullable();
            $table->timestamps();
        });

        // Game Configs
        Schema::create('game_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        // Game Events
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->default('seasonal');
            $table->string('image_url');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
        Schema::dropIfExists('x_p_s');
        Schema::dropIfExists('npcs');
        Schema::dropIfExists('enemies');
        Schema::dropIfExists('talents');
        Schema::dropIfExists('senjutsus');
        Schema::dropIfExists('game_configs');
        Schema::dropIfExists('game_events');
    }
};
