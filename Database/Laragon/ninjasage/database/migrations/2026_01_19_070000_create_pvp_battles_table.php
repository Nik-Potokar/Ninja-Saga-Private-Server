<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_battles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('host_id');
            $table->unsignedBigInteger('enemy_id');
            $table->string('mode')->nullable();
            $table->boolean('host_won')->default(false);
            $table->integer('trophy_delta')->default(0);
            $table->integer('host_trophy_before')->default(0);
            $table->integer('host_trophy_after')->default(0);
            $table->integer('enemy_trophy_before')->default(0);
            $table->integer('enemy_trophy_after')->default(0);
            $table->integer('host_level')->default(1);
            $table->integer('enemy_level')->default(1);
            $table->integer('host_rank')->default(1);
            $table->integer('enemy_rank')->default(1);
            $table->json('host_snapshot')->nullable();
            $table->json('enemy_snapshot')->nullable();
            $table->json('battle_data')->nullable();
            $table->timestamps();

            $table->index(['host_id']);
            $table->index(['enemy_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_battles');
    }
};
