<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_seasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->default(1);
            $table->boolean('active')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id')->nullable();
            $table->string('name', 50)->unique();
            $table->unsignedBigInteger('master_id'); // character_id of the master
            $table->unsignedInteger('prestige')->default(0);
            $table->unsignedInteger('max_members')->default(10);
            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedBigInteger('tokens')->default(0);
            $table->text('announcement_draft')->nullable();
            $table->text('announcement_published')->nullable();
            $table->json('buildings')->nullable();
            $table->timestamps();

            $table->foreign('season_id')->references('id')->on('clan_seasons')->nullOnDelete();
        });

        Schema::create('clan_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clan_id');
            $table->unsignedBigInteger('character_id');
            $table->enum('role', ['master', 'elder', 'member'])->default('member');
            $table->unsignedInteger('stamina')->default(5);
            $table->unsignedInteger('max_stamina')->default(5);
            $table->unsignedBigInteger('donated_golds')->default(0);
            $table->unsignedBigInteger('donated_tokens')->default(0);
            $table->timestamps();

            $table->unique(['clan_id', 'character_id']);
            $table->unique('character_id'); // a character can only be in one clan
            $table->foreign('clan_id')->references('id')->on('clans')->onDelete('cascade');
        });

        Schema::create('clan_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clan_id');
            $table->unsignedBigInteger('character_id');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('clan_id')->references('id')->on('clans')->onDelete('cascade');
        });

        Schema::create('clan_auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('character_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clan_battles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attacker_clan_id');
            $table->unsignedBigInteger('defender_clan_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->boolean('attacker_won')->default(false);
            $table->json('battle_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_battles');
        Schema::dropIfExists('clan_auth_tokens');
        Schema::dropIfExists('clan_requests');
        Schema::dropIfExists('clan_members');
        Schema::dropIfExists('clans');
        Schema::dropIfExists('clan_seasons');
    }
};
