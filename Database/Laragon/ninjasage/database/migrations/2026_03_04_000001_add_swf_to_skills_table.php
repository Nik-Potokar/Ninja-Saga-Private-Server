<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            // Stores the skill_id whose SWF file should be served for this skill
            // (e.g. "skill_59"). When the Flash client requests skill_10002.swf
            // and no such file exists, the route serves skill_59.swf instead.
            // Null means use the global SKILL_DEFAULT_SWF fallback.
            $table->string('swf')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('swf');
        });
    }
};
