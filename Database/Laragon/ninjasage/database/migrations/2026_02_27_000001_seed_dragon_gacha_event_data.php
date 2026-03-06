<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Populates the DragonGacha GameEvent row with a default config so the gacha
 * panel works out of the box.  Pools mirror the gamedata.json dragon_gacha entry.
 * Adjust item IDs via the admin panel (Events → Dragon Gacha) as needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('game_events')->where('panel', 'DragonGacha')->first();

        $defaultData = json_encode([
            'pool_weights' => [5, 25, 70],
            'draws'        => [
                'normal'         => ['qty' => 1, 'coin_cost' => 1,  'token_cost' => 25],
                'advanced'       => ['qty' => 2, 'coin_cost' => 2,  'token_cost' => 50],
                'advanced_bonus' => ['qty' => 6,                    'token_cost' => 250],
            ],
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
                    'tokens_',
                ],
                'common' => [
                    'material_773', 'material_775', 'material_776', 'material_777', 'material_778',
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
                    'gold_',
                ],
            ],
        ]);

        if ($existing) {
            // Only update if data is currently empty / null
            $currentData = $existing->data;
            if (empty($currentData) || $currentData === '[]' || $currentData === '{}' || $currentData === null) {
                DB::table('game_events')
                    ->where('panel', 'DragonGacha')
                    ->update(['data' => $defaultData, 'active' => true]);
            }
        } else {
            DB::table('game_events')->insert([
                'title'      => 'Dragon Gacha',
                'icon'       => 'dragongacha',
                'panel'      => 'DragonGacha',
                'type'       => 'feature',
                'active'     => true,
                'sort_order' => 5,
                'data'       => $defaultData,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Remove the default pool data but keep the row
        DB::table('game_events')
            ->where('panel', 'DragonGacha')
            ->update(['data' => null]);
    }
};