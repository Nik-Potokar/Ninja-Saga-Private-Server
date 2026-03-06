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
        Schema::create('character_friends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->onDelete('cascade');
            $table->foreignId('friend_id')->constrained('characters')->onDelete('cascade');
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->unique(['character_id', 'friend_id']);
        });

        Schema::create('friend_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->onDelete('cascade');
            $table->foreignId('requester_id')->constrained('characters')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['character_id', 'requester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_requests');
        Schema::dropIfExists('character_friends');
    }
};
