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
        // Items
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->unique(); // e.g. wpn_01
            $table->string('name');
            $table->integer('level')->default(1);
            $table->integer('price_gold')->default(0);
            $table->integer('price_tokens')->default(0);
            $table->string('category'); // weapon, back, set, etc.
            $table->boolean('premium')->default(false);
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        // Skills
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('skill_id')->unique();
            $table->string('name');
            $table->integer('level')->default(1);
            $table->tinyInteger('element')->default(0); // 1:Wind, 2:Fire, etc.
            $table->integer('price_gold')->default(0);
            $table->integer('price_tokens')->default(0);
            $table->boolean('premium')->default(false);
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        // Pets
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->string('pet_id')->unique(); // Kept as string for compatibility with existing IDs like 'pet_01' if any, but data will be numeric '1'
            $table->string('name');
            $table->string('swf');
            $table->integer('price_gold')->default(0);
            $table->integer('price_tokens')->default(0);
            $table->boolean('premium')->default(false);
            $table->string('icon')->nullable();
            $table->json('skills')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('pets');
    }
};
