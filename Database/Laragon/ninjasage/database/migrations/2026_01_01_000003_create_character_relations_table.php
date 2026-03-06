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
        // Character Items
        Schema::create('character_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->string('item_id');
            $table->integer('quantity')->default(1);
            $table->string('category')->nullable();
            $table->timestamps();
        });

        // Character Skills
        Schema::create('character_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->string('skill_id');
            $table->timestamps();
        });

        // Character Pets
        Schema::create('character_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->string('pet_id'); // e.g. "1" or "pet_01"
            $table->integer('level')->default(1);
            $table->bigInteger('xp')->default(0);
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Character Recruits
        Schema::create('character_recruits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id'); // Manual foreign key if needed or implicit
            $table->string('recruit_id');
            $table->timestamps();
        });

        // Character Skill Sets
        Schema::create('character_skill_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->integer('preset_index');
            $table->text('skills')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_items');
        Schema::dropIfExists('character_skills');
        Schema::dropIfExists('character_pets');
        Schema::dropIfExists('character_recruits');
        Schema::dropIfExists('character_skill_sets');
    }
};
