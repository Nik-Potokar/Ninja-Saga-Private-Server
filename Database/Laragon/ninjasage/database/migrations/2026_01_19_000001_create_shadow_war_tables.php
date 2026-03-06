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
        Schema::create('shadow_war_seasons', function (Blueprint $table) {
            $table->id();
            $table->integer('num')->index();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('shadow_war_squads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->foreignId('season_id')->constrained('shadow_war_seasons')->onDelete('cascade');
            $table->unsignedTinyInteger('squad');
            $table->integer('rank')->default(0);
            $table->integer('trophy')->default(0);
            $table->integer('energy')->default(100);
            $table->date('energy_last_reset')->nullable();
            $table->integer('energy_refills_today')->default(0);
            $table->timestamps();

            $table->unique(['character_id', 'season_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shadow_war_squads');
        Schema::dropIfExists('shadow_war_seasons');
    }
};
