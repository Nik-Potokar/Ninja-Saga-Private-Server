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
        Schema::create('pet_villa_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('slot_index'); // 0–3
            // 0=locked, 1=waiting (empty/unlocked), 2=training, 3=done (ready to checkout)
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('pet_instance_id')->nullable(); // FK to character_pets.id
            $table->unsignedInteger('training_ends_at')->nullable(); // Unix timestamp
            $table->unsignedInteger('gold_spent')->default(0);
            $table->timestamps();

            $table->unique(['character_id', 'slot_index']);
            $table->foreign('pet_instance_id')->references('id')->on('character_pets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_villa_slots');
    }
};
