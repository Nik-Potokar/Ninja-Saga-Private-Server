<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clan_seasons')) {
            Schema::create('clan_seasons', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('number')->default(1);
                $table->boolean('active')->default(false)->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('clans')) {
            Schema::create('clans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('season_id')->constrained('clan_seasons')->onDelete('cascade');
                $table->string('name', 50)->unique();
                $table->unsignedBigInteger('master_id')->index();
                $table->integer('prestige')->default(0);
                $table->integer('max_members')->default(20);
                $table->integer('gold')->default(0);
                $table->integer('tokens')->default(0);
                $table->json('buildings')->nullable();
                $table->text('announcement_published')->nullable();
                $table->text('announcement_draft')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('clan_members')) {
            Schema::create('clan_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clan_id')->constrained()->onDelete('cascade');
                $table->foreignId('character_id')->constrained()->onDelete('cascade');
                $table->string('role', 20)->default('member');
                $table->integer('stamina')->default(5);
                $table->integer('max_stamina')->default(5);
                $table->integer('donated_golds')->default(0);
                $table->integer('donated_tokens')->default(0);
                $table->timestamps();

                $table->unique('character_id');
            });
        }

        if (!Schema::hasTable('clan_requests')) {
            Schema::create('clan_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clan_id')->constrained()->onDelete('cascade');
                $table->foreignId('character_id')->constrained()->onDelete('cascade');
                $table->string('status', 20)->default('pending');
                $table->timestamps();

                $table->unique(['clan_id', 'character_id']);
            });
        }

        if (!Schema::hasTable('clan_battles')) {
            Schema::create('clan_battles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('season_id')->constrained('clan_seasons')->onDelete('cascade');
                $table->foreignId('attacker_clan_id')->constrained('clans')->onDelete('cascade');
                $table->foreignId('defender_clan_id')->constrained('clans')->onDelete('cascade');
                $table->boolean('attacker_won')->default(false);
                $table->json('battle_data')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('clan_auth_tokens')) {
            Schema::create('clan_auth_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('character_id')->constrained()->onDelete('cascade');
                $table->string('token', 64)->unique()->index();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_auth_tokens');
        Schema::dropIfExists('clan_battles');
        Schema::dropIfExists('clan_requests');
        Schema::dropIfExists('clan_members');
        Schema::dropIfExists('clans');
        Schema::dropIfExists('clan_seasons');
    }
};