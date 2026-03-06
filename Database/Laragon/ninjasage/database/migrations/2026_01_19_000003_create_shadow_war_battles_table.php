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
        Schema::create('shadow_war_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('shadow_war_seasons')->onDelete('cascade');
            $table->string('battle_code')->nullable()->index();
            $table->foreignId('character_id')->constrained('characters')->onDelete('cascade');
            $table->unsignedBigInteger('enemy_id')->nullable();
            $table->unsignedTinyInteger('character_squad')->nullable();
            $table->unsignedTinyInteger('enemy_squad')->nullable();
            $table->integer('character_level')->default(0);
            $table->integer('enemy_level')->default(0);
            $table->integer('character_rank')->default(0);
            $table->integer('enemy_rank')->default(0);
            $table->integer('character_trophy_before')->default(0);
            $table->integer('enemy_trophy_before')->default(0);
            $table->integer('character_trophy_after')->default(0);
            $table->integer('enemy_trophy_after')->default(0);
            $table->integer('trophy_delta')->default(0);
            $table->integer('total_damage')->default(0);
            $table->boolean('won')->default(false);
            $table->json('battle_data')->nullable();
            $table->integer('energy_cost')->default(0);
            $table->integer('energy_before')->default(0);
            $table->integer('energy_after')->default(0);
            $table->integer('refills_used_today')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shadow_war_battles');
    }
};
