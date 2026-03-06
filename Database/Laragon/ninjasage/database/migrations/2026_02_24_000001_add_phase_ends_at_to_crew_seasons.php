<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_seasons', function (Blueprint $table) {
            // Timestamp when the current phase ends (null = no automatic advancement)
            $table->timestamp('phase_ends_at')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('crew_seasons', function (Blueprint $table) {
            $table->dropColumn('phase_ends_at');
        });
    }
};
