<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crew_seasons')) {
            Schema::create('crew_seasons', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('number')->default(1);
                $table->boolean('active')->default(false)->index();
                $table->unsignedTinyInteger('phase')->default(1);  // 1=attack, 2=defend
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crews')) {
            Schema::create('crews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('season_id')->constrained('crew_seasons')->onDelete('cascade');
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

        if (!Schema::hasTable('crew_members')) {
            Schema::create('crew_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('crew_id')->constrained()->onDelete('cascade');
                $table->foreignId('character_id')->constrained()->onDelete('cascade');
                $table->string('role', 20)->default('member'); // master, elder, member
                $table->unsignedTinyInteger('battle_role')->default(1); // 1=attacker, 2=defender
                $table->string('role_limit_at', 50)->default('');
                $table->integer('stamina')->default(50);
                $table->integer('max_stamina')->default(50);
                $table->integer('donated_golds')->default(0);
                $table->integer('donated_tokens')->default(0);
                $table->integer('minigame_energy')->default(3);
                $table->timestamp('prestige_boost_expires_at')->nullable();
                $table->timestamps();

                $table->unique('character_id');
            });
        }

        if (!Schema::hasTable('crew_requests')) {
            Schema::create('crew_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('crew_id')->constrained()->onDelete('cascade');
                $table->foreignId('character_id')->constrained()->onDelete('cascade');
                $table->string('status', 20)->default('pending');
                $table->timestamps();

                $table->unique(['crew_id', 'character_id']);
            });
        }

        if (!Schema::hasTable('crew_battles')) {
            Schema::create('crew_battles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('season_id')->constrained('crew_seasons')->onDelete('cascade');
                $table->unsignedBigInteger('castle_id')->nullable();
                $table->foreignId('attacker_crew_id')->constrained('crews')->onDelete('cascade');
                $table->unsignedBigInteger('defender_crew_id')->nullable();
                $table->boolean('attacker_won')->default(false);
                $table->json('battle_data')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('crew_castles')) {
            Schema::create('crew_castles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('season_id')->constrained('crew_seasons')->onDelete('cascade');
                $table->unsignedTinyInteger('castle_index'); // 0-6
                $table->string('name', 100)->default('');
                $table->unsignedBigInteger('owner_crew_id')->nullable()->index();
                $table->unsignedInteger('wall_hp')->default(100);
                $table->unsignedInteger('defender_hp')->default(100);
                $table->timestamp('last_recovery_at')->nullable();
                $table->timestamps();

                $table->unique(['season_id', 'castle_index']);
            });
        }

        if (!Schema::hasTable('crew_auth_tokens')) {
            Schema::create('crew_auth_tokens', function (Blueprint $table) {
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
        Schema::dropIfExists('crew_auth_tokens');
        Schema::dropIfExists('crew_castles');
        Schema::dropIfExists('crew_battles');
        Schema::dropIfExists('crew_requests');
        Schema::dropIfExists('crew_members');
        Schema::dropIfExists('crews');
        Schema::dropIfExists('crew_seasons');
    }
};
