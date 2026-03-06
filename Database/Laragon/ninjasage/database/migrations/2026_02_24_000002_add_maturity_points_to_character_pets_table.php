<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_pets', function (Blueprint $table) {
            $table->unsignedInteger('maturity_points')->default(0)->after('xp');
        });
    }

    public function down(): void
    {
        Schema::table('character_pets', function (Blueprint $table) {
            $table->dropColumn('maturity_points');
        });
    }
};
