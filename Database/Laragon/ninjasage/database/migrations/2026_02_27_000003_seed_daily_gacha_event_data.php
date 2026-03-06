<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the DailyGacha GameEvent row with a working default config.
 * The client (DailyGacha.as) calls ChristmasEvent2021.* endpoints.
 * Gacha coin = material_874.
 */
return new class extends Migration
{
    private function defaultData(): array
    {
        return [
            'pool_weights' => [5, 25, 70],
            'pool' => [
                'top' => [
                    'tokens_2000',
                    'wpn_1121', 'wpn_1122', 'wpn_980', 'wpn_986', 'wpn_991', 'wpn_992',
                    'wpn_1014', 'wpn_1018', 'wpn_1034', 'wpn_1035', 'wpn_1036', 'wpn_1044',
                    'back_418', 'back_422', 'back_426', 'back_435', 'back_436',
                    'back_458', 'back_466', 'back_476', 'back_477', 'back_478', 'back_480',
                    'pet_goldclowndragon', 'pet_celebrationclowndragon', 'pet_icebluedragon',
                    'pet_lightningdrake', 'pet_undeadchaindragon', 'pet_darkthundertripledragon',
                    'pet_dualcannontripledragon', 'pet_minikirin', 'pet_earthlavadragonturtle',
                    'material_819', 'material_820', 'material_821', 'material_822', 'material_823',
                    'material_205',
                ],
                'mid' => [
                    'hair_223_%s', 'hair_225_%s', 'hair_226_%s', 'hair_229_%s', 'hair_230_%s',
                    'hair_231_%s', 'hair_233_%s', 'hair_248_%s', 'hair_250_%s', 'hair_251_%s',
                    'hair_252_%s',
                    'set_839_%s', 'set_840_%s', 'set_841_%s', 'set_842_%s', 'set_843_%s',
                    'set_844_%s', 'set_845_%s', 'set_846_%s', 'set_847_%s', 'set_848_%s',
                    'set_849_%s', 'set_850_%s',
                    'material_200', 'material_201', 'material_202', 'material_203', 'material_204',
                    'material_1001',
                    'essential_03', 'essential_04', 'essential_05',
                    'item_52', 'item_54',
                    'tokens_500',
                ],
                'common' => [
                    'material_874', 'material_775', 'material_776', 'material_777', 'material_778',
                    'material_779', 'material_780', 'material_781', 'material_782', 'material_783',
                    'material_784', 'material_785', 'material_786', 'material_787', 'material_788',
                    'material_789', 'material_790', 'material_791', 'material_792', 'material_793',
                    'material_794', 'material_795', 'material_796', 'material_797', 'material_798',
                    'material_799', 'material_800', 'material_801', 'material_802', 'material_803',
                    'material_804', 'material_805', 'material_806', 'material_807', 'material_808',
                    'material_809',
                    'item_49', 'item_50', 'item_51',
                    'item_33', 'item_34', 'item_35', 'item_36',
                    'item_40', 'item_39', 'item_38', 'item_37',
                    'item_44', 'item_43', 'item_42', 'item_41',
                    'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28', 'item_27',
                    'item_26', 'item_25', 'item_23', 'item_22', 'item_21', 'item_20', 'item_19',
                    'item_18', 'item_17', 'item_16', 'item_15', 'item_14', 'item_13', 'item_12',
                    'item_11', 'item_10', 'item_09', 'item_08', 'item_07', 'item_06', 'item_05',
                    'item_04', 'item_03', 'item_02',
                    'gold_50000',
                ],
            ],
            // Bonus milestone rewards (client shows up to 8 slots, indices 0–7)
            'bonus_rewards' => [
                ['id' => 'tokens_100',       'req' => 5],
                ['id' => 'tokens_250',       'req' => 10],
                ['id' => 'material_205',     'req' => 25],
                ['id' => 'tokens_500',       'req' => 50],
                ['id' => 'material_874:5',   'req' => 75],
                ['id' => 'tokens_1000',      'req' => 100],
                ['id' => 'material_205:3',   'req' => 150],
                ['id' => 'tokens_2000',      'req' => 200],
            ],
        ];
    }

    public function up(): void
    {
        $existing = DB::table('game_events')->where('panel', 'DailyGacha')->first();

        if ($existing) {
            // Only overwrite if data is empty/null (don't clobber admin edits)
            $currentData = $existing->data;
            if (empty($currentData) || $currentData === '[]' || $currentData === '{}' || $currentData === null) {
                DB::table('game_events')
                    ->where('panel', 'DailyGacha')
                    ->update(['data' => json_encode($this->defaultData()), 'updated_at' => now()]);
            }
        } else {
            DB::table('game_events')->insert([
                'title'      => 'Daily Gacha',
                'icon'       => 'dailygacha',
                'panel'      => 'DailyGacha',
                'type'       => 'feature',
                'active'     => true,
                'sort_order' => 4,
                'data'       => json_encode($this->defaultData()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('game_events')
            ->where('panel', 'DailyGacha')
            ->update(['data' => null]);
    }
};