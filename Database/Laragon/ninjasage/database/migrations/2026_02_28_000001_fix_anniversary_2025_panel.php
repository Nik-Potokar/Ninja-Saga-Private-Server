<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixes the "Anniversary 2025" seasonal event which was incorrectly set to
 * panel = "ConfrontingDeathMenu".
 *
 * Root cause: Two different events shared the same panel name, causing the
 * Anniversary event to load the Confronting Death SWF instead of its own.
 * ConfrontingDeathMenu.as hardcodes AMF calls to "ConfrontingDeathEvent2025.*",
 * so both events conflicted and the Anniversary was effectively broken.
 *
 * Fix: Switch Anniversary 2025 to panel = "HalloweenMenu" which uses the
 * newly-created HalloweenEvent2025Service. The HalloweenMenu SWF has the
 * same battle+milestone gameplay structure (multi-boss selection, 8 milestones)
 * and calls "HalloweenEvent2025.*" AMF methods exclusively.
 *
 * The data JSON is replaced with the HalloweenMenu-compatible format.
 * Item IDs below are placeholders — edit them in Admin → Game Events afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        $anniversary = DB::table('game_events')
            ->whereRaw("LOWER(title) LIKE '%anniversary%'")
            ->where('panel', 'ConfrontingDeathMenu')
            ->first();

        if (!$anniversary) {
            // Nothing to fix — either already migrated or row doesn't exist.
            return;
        }

        $data = json_encode([
            'energy_max'        => 8,
            'energy_cost'       => 1,
            'refill_token_cost' => 50,
            'bosses'            => [
                [
                    'id'          => ['enemy_anniversary_1'],
                    'name'        => 'Anniversary Boss',
                    'description' => 'A fearsome foe from the past year.',
                    'levels'      => [-5, 5],
                    'gold'        => 'level*100',
                    'rewards'     => ['material_anniversary_1:3', 'gold_10000'],
                    'background'  => 'field_bg',
                ],
            ],
            'milestone_battle' => [
                ['id' => 'item_%s_hair_anniversary', 'requirement' => 5,   'quantity' => 1],
                ['id' => 'item_%s_set_anniversary',  'requirement' => 10,  'quantity' => 1],
                ['id' => 'gold_50000',               'requirement' => 20,  'quantity' => 1],
                ['id' => 'tp_200',                   'requirement' => 30,  'quantity' => 1],
                ['id' => 'item_%s_back_anniversary', 'requirement' => 50,  'quantity' => 1],
                ['id' => 'gold_100000',              'requirement' => 70,  'quantity' => 1],
                ['id' => 'skill_%s_anniversary_1',   'requirement' => 90,  'quantity' => 1],
                ['id' => 'skill_%s_anniversary_2',   'requirement' => 120, 'quantity' => 1],
            ],
            'rewards_preview' => [
                'hair'   => [],
                'set'    => [],
                'back'   => [],
                'weapon' => [],
                'skill'  => [],
            ],
        ]);

        DB::table('game_events')
            ->where('id', $anniversary->id)
            ->update([
                'panel'      => 'HalloweenMenu',
                'data'       => $data,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Revert the panel name change (data is left as-is since we don't know
        // what the original data contained at the time of migration).
        DB::table('game_events')
            ->whereRaw("LOWER(title) LIKE '%anniversary%'")
            ->where('panel', 'HalloweenMenu')
            ->update([
                'panel'      => 'ConfrontingDeathMenu',
                'updated_at' => now(),
            ]);
    }
};
