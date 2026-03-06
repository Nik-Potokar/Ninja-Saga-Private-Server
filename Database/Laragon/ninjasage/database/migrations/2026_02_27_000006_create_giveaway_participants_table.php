<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giveaway_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giveaway_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('character_id');
            $table->timestamps();

            $table->unique(['giveaway_id', 'character_id']);
            $table->index('character_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giveaway_participants');
    }
};
