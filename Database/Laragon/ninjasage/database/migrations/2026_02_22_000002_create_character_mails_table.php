<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('sender')->default('System');
            $table->text('body')->nullable();
            $table->string('type')->default('system');
            $table->json('rewards')->nullable();
            $table->boolean('claimed')->default(false);
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_mails');
    }
};
