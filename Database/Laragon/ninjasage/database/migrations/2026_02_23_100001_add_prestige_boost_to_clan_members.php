<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clan_members', function (Blueprint $table) {
            $table->timestamp('prestige_boost_expires_at')->nullable()->after('donated_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('clan_members', function (Blueprint $table) {
            $table->dropColumn('prestige_boost_expires_at');
        });
    }
};
