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
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('level')->default(1);
            $table->bigInteger('xp')->default(0);
            $table->tinyInteger('gender')->default(0); // 0: Male, 1: Female
            $table->integer('rank')->default(1); // 1: Genin, 3: Chunin, 5: Jounin, 7: Sp. Jounin, 8: Sannin, 10: Kage
            $table->string('class')->nullable();
            
            $table->integer('gold')->default(1000);
            $table->integer('tp')->default(0);
            $table->integer('prestige')->default(0);
            $table->integer('ss')->default(0); // Senjutsu Stones?

            // Elements
            $table->integer('element_1')->default(0);
            $table->integer('element_2')->default(0);
            $table->integer('element_3')->default(0);
            
            // Talents (Configuration/Slots)
            $table->string('talent_1')->nullable();
            $table->string('talent_2')->nullable();
            $table->string('talent_3')->nullable();
            
            // Appearance
            $table->string('hair_style')->nullable();
            $table->string('hair_color')->nullable();
            $table->string('skin_color')->nullable();

            // Equipment
            $table->string('equipment_weapon')->nullable();
            $table->string('equipment_back')->nullable();
            $table->string('equipment_clothing')->nullable();
            $table->string('equipment_accessory')->nullable();
            $table->text('equipment_skills')->nullable();
            $table->string('equipment_pet')->nullable();

            // Rewards
            $table->text('claimed_welcome_rewards')->nullable();

            // Points
            $table->integer('point_wind')->default(0);
            $table->integer('point_fire')->default(0);
            $table->integer('point_lightning')->default(0);
            $table->integer('point_water')->default(0);
            $table->integer('point_earth')->default(0);
            $table->integer('point_free')->default(0);

            // Exams
            $table->string('chunin_exam_progress')->default('1,0,0,0,0');
            $table->boolean('chunin_claimed')->default(false);
            $table->string('jounin_exam_progress')->default('1,0,0,0,0');
            $table->boolean('jounin_claimed')->default(false);
            $table->string('special_jounin_exam_progress')->default('1,0,0,0,0,0,0,0,0,0,0,0,0');
            $table->boolean('special_jounin_claimed')->default(false);
            $table->string('ninja_tutor_exam_progress')->default('1,0,0,0,0,0');
            $table->boolean('ninja_tutor_claimed')->default(false);

            // Daily Claims
            $table->timestamp('daily_token_claimed_at')->nullable();
            $table->timestamp('daily_xp_claimed_at')->nullable();
            $table->timestamp('daily_scroll_claimed_at')->nullable();
            $table->timestamp('double_xp_expire_at')->nullable();
            $table->integer('xp_bonus_rate')->default(0);

            // Daily Features
            $table->date('daily_scratch_date')->nullable();
            $table->integer('daily_scratch_consecutive')->default(1);
            $table->integer('daily_scratch_count')->default(0);
            $table->unsignedInteger('scratch_grand_progress')->default(0);
            $table->unsignedInteger('scratch_rare_progress')->default(0);

            $table->date('daily_roulette_date')->nullable();
            $table->integer('daily_roulette_consecutive')->default(1);
            $table->integer('daily_roulette_count')->default(0);

            // Attendance
            $table->json('attendance_days')->nullable();
            $table->json('attendance_rewards')->nullable();
            $table->date('attendance_last_reset')->nullable();

            // Learned Skills (JSON/Text blob of IDs?)
            $table->text('talent_skills')->nullable();
            $table->text('senjutsu_skills')->nullable();
            $table->string('senjutsu_type')->nullable();
            $table->text('senjutsu_equipped_skills')->nullable();

            // Minigames
            $table->string('hunting_house_tries')->nullable();
            $table->date('hunting_house_date')->nullable();
            $table->string('eudemon_garden_tries')->nullable();
            $table->date('eudemon_garden_date')->nullable();
            $table->boolean('recruitable')->default(true);

            // PVP
            $table->integer('pvp_played')->default(0);
            $table->integer('pvp_won')->default(0);
            $table->integer('pvp_lost')->default(0);
            $table->integer('pvp_points')->default(0);
            $table->integer('pvp_trophy')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
