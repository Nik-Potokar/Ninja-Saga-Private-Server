<?php

namespace Database\Seeders;

use App\Models\GameEvent;
use Illuminate\Database\Seeder;

class GameEventSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing rows so the seeder is idempotent on re-run.
        GameEvent::truncate();

        // ----------------------------------------------------------------
        // SEASONAL EVENTS  (type = 'seasonal')
        // sort_order controls display order inside the seasonal section.
        // ----------------------------------------------------------------
        $seasonal = [
            [
                'title'       => 'Yuki Onna: Eternal Winter',
                'description' => 'Fight back the blizzard and stop the Eternal Winter before the Christmas Star fades away.',
                'date'        => '25/12 - 25/03, 2026',
                'image_url'   => 'https://ns-assets.ninjasage.id/tmp/yukionna.jpg',
                'panel'       => 'ChristmasMenu',
                'sort_order'  => 1,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2331', 'skill_2330', 'skill_521', 'skill_552', 'skill_756',
                            'wpn_2405', 'wpn_2406', 'wpn_2407', 'wpn_2408', 'wpn_1029', 'wpn_902',
                            'wpn_2197', 'back_2402', 'back_2403', 'back_2404', 'back_2405', 'back_2406',
                            'back_386', 'back_2188', 'back_2189', 'material_1001', 'essential_03',
                            'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2332', 'tokens_2000'],
                        'common' => [
                            'hair_2367_%s', 'hair_2368_%s', 'hair_2369_%s', 'hair_2370_%s',
                            'hair_2371_%s', 'hair_2372_%s', 'hair_2373_%s', 'hair_2374_%s',
                            'set_2408_%s', 'set_2409_%s', 'set_2410_%s', 'set_2411_%s',
                            'set_2412_%s', 'set_2413_%s', 'set_2414_%s', 'set_2415_%s',
                            'material_2226', 'material_2227', 'material_2228', 'material_2231',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2231', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2363_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2404_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2398',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2401',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    'bosses' => [
                        ['id' => ['ene_2117'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Yuki Onna Warrior', 'levels' => [0, 5], 'rewards' => ['material_2226', 'material_2228', 'material_2231'], 'background' => 'mission_1065', 'description' => 'Yuki Onna Warrior is a warrior that is known for its ability to use shuriken to attack its enemies.'],
                        ['id' => ['ene_2118'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Snow Spirit',       'levels' => [0, 5], 'rewards' => ['material_2226', 'material_2227', 'material_2231'], 'background' => 'mission_1065', 'description' => 'Snow Spirit is a spirit that is known for its ability to use snow to attack its enemies.'],
                        ['id' => ['ene_2119'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Yuki Onna',         'levels' => [0, 5], 'rewards' => ['material_2226', 'material_2229', 'material_2231'], 'background' => 'mission_1065', 'description' => 'Yuki Onna is a spirit that is known for its ability to use snow to attack its enemies.'],
                    ],
                    'minigame'  => ['material_2230', 'material_2231'],
                    'new_year'  => ['hair_2364_%s', 'set_2405_%s'],
                    'rewards_preview' => [
                        'set'    => ['set_2406_%s', 'set_2407_%s'],
                        'back'   => ['back_2400', 'back_2401', 'back_2399'],
                        'hair'   => ['hair_2365_%s', 'hair_2366_%s'],
                        'skill'  => ['skill_2327', 'skill_2328', 'skill_2329'],
                        'weapon' => ['wpn_2403', 'wpn_2404'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2229', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2362_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2403_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2397',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2400',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            [
                'title'       => 'Feast of Gratitude: Celebration',
                'description' => 'Ninjas must compete, collect, and defend the feast to ensure every villager enjoys the celebration.',
                'date'        => '04/12 - 04/03, 2026',
                'image_url'   => 'https://ns-assets.ninjasage.id/tmp/thanksgiving2025.jpg',
                'panel'       => 'FeastOfGratitudeMenu',
                'sort_order'  => 2,
                'data'        => [
                    // ── Server-side config (read by ThanksGivingEvent2025Service) ──
                    'energy_max'        => 10,
                    'energy_cost'       => 1,
                    'refill_token_cost' => 50,
                    // Rewards on every battle win.
                    'rewards_win'       => ['material_tg_1:3', 'gold_10000'],
                    // milestone_battle is also the milestone source for the service
                    // (same format as the client; service reads both key variants).
                    'milestone_battle'  => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2219', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2350_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2390_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2383',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2386',      'quantity' => 1,  'requirement' => 750],
                    ],
                    // One-time purchasable package.
                    'package' => [
                        'price'   => [200, 150],  // [non_member, member]
                        'rewards' => ['skill_2313', 'back_2383', 'gold_100000', 'material_tg_1:10'],
                    ],
                    // ── Client-side GameData keys (sent via EventsService.get) ──
                    'bosses'           => [],
                    'paket'            => [],
                    'rewards_preview'  => [],
                ],
            ],
            [
                'title'       => 'Confronting Death Event 2025',
                'description' => 'Lord of the Underworld is the ruler of the underworld. He is the one who controls the dead and the living. He is the one who controls the fate of the world.',
                'date'        => '04/11 - 04/02, 2026',
                'image_url'   => 'https://ns-assets.ninjasage.id/tmp/confrontingdeath2025.png',
                'panel'       => 'ConfrontingDeathMenu',
                'sort_order'  => 3,
                'data'        => [
                    // ── Server-side config (read by ConfrontingDeathEvent2025Service) ──
                    'energy_max'        => 8,
                    'energy_cost'       => 1,
                    'refill_token_cost' => 50,
                    // Rewards on every battle win (materials dropped into inventory).
                    'rewards_win'       => ['material_2216:2', 'material_2217:2', 'gold_10000'],
                    // milestone_battle is used by both the client (display) and server (grants).
                    // training is used by both the client (display) and server (buy logic).
                    // ── Client-side GameData keys ──
                    // bosses.id is keyed by battle phase; background is also an array
                    'bosses' => [
                        'id' => [
                            'battle_1' => ['ene_2107', 'ene_2108', 'ene_2109'],
                            'battle_2' => ['ene_2112', 'ene_2110', 'ene_2111'],
                        ],
                        'xp'          => 'level * 2500 / 60',
                        'gold'        => 'level * 2500 / 60',
                        'name'        => 'Lord of the Underworld - Hades',
                        'levels'      => [0, 5],
                        'rewards'     => ['material_2216', 'material_2217', 'material_2218', 'material_2219', 'material_2220'],
                        'background'  => ['mission_1061', 'mission_1062'],
                        'description' => 'Lord of the Underworld is the ruler of the underworld. He is the one who controls the dead and the living. He is the one who controls the fate of the world.',
                    ],
                    'training' => [
                        ['id' => 'skill_2313', 'name' => 'Erebos Beam',         'price' => [3999, 2999]],
                        ['id' => 'skill_2314', 'name' => 'Advance Erebos Beam', 'price' => [1999, 1999]],
                    ],
                    'dialogues' => [
                        'scene_1' => [
                            ['npc' => 'Fire Anbu',             'scene' => 'kageroom', 'dialogue' => 'Kage! The dead are rising and attacking the village!',                           'position' => 'left'],
                            ['npc' => 'Kage',                                         'dialogue' => 'What?! Undead… this must be Hades at work. Ninjas, prepare for battle!',         'position' => 'right'],
                            ['npc' => 'Cerberus',              'scene' => 'village',  'dialogue' => 'GRRR... None shall pass! The souls of this village belong to Lord Hades!',       'position' => 'right'],
                            ['npc' => 'Undead Soldier A & B',                         'dialogue' => 'GRAAAHH!! Souls… feed… Cerberus…',                                              'position' => 'right'],
                            ['npc' => 'Fire Anbu',                                    'dialogue' => 'We won\'t let monsters from the underworld destroy our home! Attack!',            'position' => 'left'],
                        ],
                        'scene_2' => [
                            ['npc' => 'Cerberus', 'scene' => 'village', 'dialogue' => 'Pathetic mortals… you think defeating me frees you? Hades will unleash true darkness upon you!',         'position' => 'right'],
                            ['npc' => 'Kage',                           'dialogue' => 'The gate to the underworld is open. Ninjas, we descend and face Hades at his throne!',                   'position' => 'left'],
                            ['npc' => 'Hades',   'scene' => 'throne',   'dialogue' => 'So you defeated my guardian, Cerberus. Impressive.',                                                      'position' => 'right'],
                            ['npc' => 'Hades',   'scene' => 'throne',   'dialogue' => 'But you now stand before death itself. Every breath you take, every step you walk… all ends with me.',   'position' => 'right'],
                            ['npc' => 'Hades',   'scene' => 'throne',   'dialogue' => 'Challenge me, mortals, if you dare!',                                                                      'position' => 'right'],
                        ],
                        'scene_3' => [
                            ['npc' => 'Hades', 'scene' => 'throne', 'dialogue' => 'Hah… you resist even death… but nothing lasts… all souls… return to me… eventually…', 'position' => 'right'],
                            ['npc' => 'Kage',                       'dialogue' => 'Maybe so, but not today. Today, the living stand victorious.',                           'position' => 'left'],
                            ['npc' => 'Kage',                       'dialogue' => 'Ninjas, we\'ve conquered death\'s domain. It\'s time to return home.',                   'position' => 'left'],
                        ],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2391_%s', 'set_2392_%s'],
                        'back'   => ['back_2384', 'back_2385', 'back_2386'],
                        'hair'   => ['hair_2351_%s', 'hair_2352_%s'],
                        'skill'  => ['skill_2311', 'skill_2312'],
                        'weapon' => ['wpn_2387', 'wpn_2388', 'wpn_2389'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2219', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2350_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2390_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2383',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2386',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Valentine's 2025 – inactive by default; set active = true to
            // show it in the catalog again when the event runs.
            // All gameplay configuration is stored in the data column.
            // -----------------------------------------------------------------
            [
                'title'       => "Valentine's Day 2025",
                'description' => 'The Queen of Hearts challenges all ninjas. Defeat her knights, collect materials, and claim exclusive Valentine\'s rewards.',
                'date'        => null,   // fill in the actual date range
                'image_url'   => '',     // fill in the event banner URL
                'panel'       => 'ValentinesMenu',
                'active'      => false,  // flip to true when the event is live
                'sort_order'  => 4,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2240', 'skill_2241', 'skill_621', 'skill_767', 'skill_492',
                            'skill_2071', 'wpn_2307', 'wpn_2308', 'wpn_2309', 'wpn_2310',
                            'wpn_2087', 'wpn_2088', 'wpn_2089', 'back_2296', 'back_2297',
                            'back_2298', 'back_2299', 'back_2300', 'back_2085', 'back_2084',
                            'back_485', 'essential_14', 'material_1001', 'essential_03',
                            'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2239', 'tokens_2000'],
                        'common' => [
                            'hair_2257_%s', 'hair_2258_%s', 'hair_2259_%s', 'hair_2260_%s',
                            'hair_2261_%s', 'hair_2262_%s', 'hair_2263_%s', 'hair_2264_%s',
                            'set_2297_%s', 'set_2298_%s', 'set_2299_%s', 'set_2300_%s',
                            'set_2301_%s', 'set_2302_%s', 'set_2303_%s', 'set_2304_%s',
                            'material_2157', 'material_2158', 'material_2159', 'material_2160',
                            'material_2161', 'material_2162', 'material_2163', 'material_2164',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2164', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2254_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2294_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2292',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2304',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    'bosses' => [
                        [
                            'id'          => ['ene_2081'],
                            'name'        => 'Spade Knight',
                            'levels'      => [0, 5],
                            'xp'          => 'level * 2500 / 60',
                            'gold'        => 'level * 2500 / 60',
                            'rewards'     => ['material_2157', 'material_2161', 'material_2164'],
                            'background'  => 'mission_1046',
                            'description' => '',
                        ],
                        [
                            'id'          => ['ene_2082'],
                            'name'        => 'Club Knight',
                            'levels'      => [0, 5],
                            'xp'          => 'level * 2500 / 60',
                            'gold'        => 'level * 2500 / 60',
                            'rewards'     => ['material_2159', 'material_2161', 'material_2164'],
                            'background'  => 'mission_1046',
                            'description' => '',
                        ],
                        [
                            'id'          => ['ene_2083'],
                            'name'        => 'Heart Knight',
                            'levels'      => [0, 5],
                            'xp'          => 'level * 2500 / 60',
                            'gold'        => 'level * 2500 / 60',
                            'rewards'     => ['material_2158', 'material_2162', 'material_2164'],
                            'background'  => 'mission_1046',
                            'description' => '',
                        ],
                        [
                            'id'          => ['ene_2084'],
                            'name'        => 'Diamond Knight',
                            'levels'      => [0, 5],
                            'xp'          => 'level * 2500 / 60',
                            'gold'        => 'level * 2500 / 60',
                            'rewards'     => ['material_2160', 'material_2162', 'material_2164'],
                            'background'  => 'mission_1046',
                            'description' => '',
                        ],
                        [
                            'id'          => ['ene_2085'],
                            'name'        => 'Queen of Hearts',
                            'levels'      => [0, 5],
                            'xp'          => 'level * 2500 / 60',
                            'gold'        => 'level * 2500 / 60',
                            'rewards'     => ['material_2161', 'material_2162', 'material_2163', 'material_2164'],
                            'background'  => 'mission_1046',
                            'description' => 'The Queen of Heart is ruthless and controlling, ruling through fear and manipulation.',
                        ],
                    ],
                    'training' => [
                        ['id' => 'skill_567', 'name' => 'Kinjutsu: Woven Clew',        'price' => [20250000]],
                        ['id' => 'skill_566', 'name' => 'Kinjutsu: Golden Woven Clew', 'price' => [2499, 1999]],
                    ],
                    'boss_unlock' => [
                        ['id' => 'material_2157', 'requirement' => 50],
                        ['id' => 'material_2158', 'requirement' => 50],
                        ['id' => 'material_2159', 'requirement' => 50],
                        ['id' => 'material_2160', 'requirement' => 50],
                    ],
                    'npc_recruit'     => ['npc_105', 'npc_106'],
                    'rewards_preview' => [
                        'set'    => ['set_2295_%s', 'set_2296_%s'],
                        'back'   => ['back_2293', 'back_2294'],
                        'hair'   => ['hair_2255_%s', 'hair_2256_%s'],
                        'skill'  => ['skill_2237', 'skill_2238'],
                        'weapon' => ['wpn_2305', 'wpn_2306'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',  'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2163','quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2253_%s', 'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05', 'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2293_%s',  'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',   'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2291',    'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2303',     'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Anniversary 2025
            // -----------------------------------------------------------------
            [
                'title'       => 'Anniversary 2025',
                'description' => 'Celebrate the anniversary with the Seven Lucky Gods. Collect materials, craft lucky bags, and earn exclusive anniversary rewards.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'AnniversaryMenu',
                'active'      => false,
                'sort_order'  => 5,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2246', 'skill_2247', 'skill_2166', 'skill_2167', 'skill_2168',
                            'wpn_2313', 'wpn_2314', 'wpn_2315', 'wpn_1185', 'wpn_2212', 'wpn_2213',
                            'wpn_2214', 'back_2305', 'back_2306', 'back_2307', 'back_2308', 'back_2309',
                            'back_2199', 'back_2200', 'back_2201', 'essential_14', 'material_1001',
                            'essential_03', 'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2249', 'tokens_2000'],
                        'common' => [
                            'hair_2268_%s', 'hair_2269_%s', 'hair_2270_%s', 'hair_2271_%s',
                            'hair_2272_%s', 'hair_2273_%s', 'hair_2274_%s', 'hair_2275_%s',
                            'set_2308_%s', 'set_2309_%s', 'set_2310_%s', 'set_2311_%s',
                            'set_2312_%s', 'set_2313_%s', 'set_2314_%s', 'set_2315_%s',
                            'material_2165', 'material_2166', 'material_2167', 'material_2168',
                            'material_2169', 'material_2170', 'material_2171', 'material_2172',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2165', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2267_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2307_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2304',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2312',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    // bosses is a keyed object indexed by enemy id
                    'bosses' => [
                        'ene_403' => ['id' => ['ene_403'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Ebisu',       'levels' => [0, 5], 'rewards' => ['material_2166', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_407' => ['id' => ['ene_407'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Daikokuten', 'levels' => [0, 5], 'rewards' => ['material_2169', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_411' => ['id' => ['ene_411'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Fukurokuju','levels' => [0, 5], 'rewards' => ['material_2172', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_415' => ['id' => ['ene_415'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Jurojin',   'levels' => [0, 5], 'rewards' => ['material_2171', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_419' => ['id' => ['ene_419'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Hotei',     'levels' => [0, 5], 'rewards' => ['material_2167', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_423' => ['id' => ['ene_423'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Bishamonten','levels'=> [0, 5], 'rewards' => ['material_2168', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_427' => ['id' => ['ene_427'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Benzaiten', 'levels' => [0, 5], 'rewards' => ['material_2170', 'material_2165'], 'background' => 'mission_1047', 'description' => ''],
                        'ene_431' => ['id' => ['ene_431'], 'xp' => 'level * 12500 / 10','gold' => 'level * 12500 / 10','name' => 'Taikoman',  'levels' => [0, 5], 'rewards' => ['material_2173', 'material_2165'], 'background' => 'mission_1048', 'description' => ''],
                    ],
                    'minigame' => ['material_2174', 'material_2175', 'material_2176'],
                    'spending' => [
                        ['id' => 'essential_01',   'quantity' => 3,  'requirement' => 10],
                        ['id' => 'tp_200',         'quantity' => 1,  'requirement' => 25],
                        ['id' => 'material_1001',  'quantity' => 1,  'requirement' => 50],
                        ['id' => 'material_69',    'quantity' => 25, 'requirement' => 100],
                        ['id' => 'tp_250',         'quantity' => 1,  'requirement' => 250],
                        ['id' => 'ss_500',         'quantity' => 1,  'requirement' => 500],
                        ['id' => 'hair_2265_%s',   'quantity' => 1,  'requirement' => 1000],
                        ['id' => 'set_2305_%s',    'quantity' => 1,  'requirement' => 2500],
                        ['id' => 'pet_sacredlion', 'quantity' => 1,  'requirement' => 5000],
                        ['id' => 'accessory_2117', 'quantity' => 1,  'requirement' => 10000],
                        ['id' => 'back_2301',      'quantity' => 1,  'requirement' => 20000],
                        ['id' => 'wpn_2311',       'quantity' => 1,  'requirement' => 30000],
                        ['id' => 'skill_2242',     'quantity' => 1,  'requirement' => 50000],
                    ],
                    'training' => [
                        ['id' => 'skill_690', 'name' => 'Kinjutsu: Throwing Shield',      'price' => [9999, 8999]],
                        ['id' => 'skill_691', 'name' => 'Kinjutsu: Throwing Shield Plus', 'price' => [7499, 5999]],
                    ],
                    'fukubukuro' => [
                        'materials' => ['material_2166', 'material_2167', 'material_2168', 'material_2169', 'material_2170', 'material_2171', 'material_2172'],
                        'red' => [
                            ['rewards' => ['gold_100000', 'tokens_10', 'tp_50', 'hair_2266_%s'],   'requirement' => [['id' => 'material_2166', 'quantity' => 1], ['id' => 'material_2167', 'quantity' => 1], ['id' => 'material_2168', 'quantity' => 1]]],
                            ['rewards' => ['gold_100000', 'tokens_10', 'tp_50', 'material_2165:3'],'requirement' => [['id' => 'material_2168', 'quantity' => 2], ['id' => 'material_2169', 'quantity' => 1], ['id' => 'material_2170', 'quantity' => 2], ['id' => 'material_2171', 'quantity' => 1]]],
                            ['rewards' => ['gold_100000', 'tokens_50', 'tp_100', 'set_2306_%s'],   'requirement' => [['id' => 'material_2167', 'quantity' => 1], ['id' => 'material_2168', 'quantity' => 3], ['id' => 'material_2169', 'quantity' => 3]]],
                            ['rewards' => ['gold_100000', 'tokens_10', 'tp_50', 'material_2165:5'],'requirement' => [['id' => 'material_2167', 'quantity' => 3], ['id' => 'material_2168', 'quantity' => 3], ['id' => 'material_2170', 'quantity' => 1], ['id' => 'material_2171', 'quantity' => 2]]],
                        ],
                        'gold' => [
                            ['rewards' => ['gold_1000000', 'tokens_100', 'tp_200', 'skill_2243'], 'requirement' => [['id' => 'material_2166', 'quantity' => 5], ['id' => 'material_2167', 'quantity' => 7], ['id' => 'material_2168', 'quantity' => 5], ['id' => 'material_2169', 'quantity' => 7], ['id' => 'material_2170', 'quantity' => 5], ['id' => 'material_2171', 'quantity' => 7], ['id' => 'material_2172', 'quantity' => 5]]],
                        ],
                        'grey' => [
                            ['rewards' => ['gold_500000', 'tokens_50', 'tp_150', 'back_2303'], 'requirement' => [['id' => 'material_2167', 'quantity' => 2], ['id' => 'material_2169', 'quantity' => 1], ['id' => 'material_2170', 'quantity' => 5], ['id' => 'material_2171', 'quantity' => 3], ['id' => 'material_2172', 'quantity' => 5]]],
                            ['rewards' => ['gold_500000', 'tokens_50', 'tp_150', 'wpn_1097'],  'requirement' => [['id' => 'material_2166', 'quantity' => 3], ['id' => 'material_2167', 'quantity' => 5], ['id' => 'material_2168', 'quantity' => 5], ['id' => 'material_2169', 'quantity' => 5], ['id' => 'material_2170', 'quantity' => 2], ['id' => 'material_2171', 'quantity' => 2], ['id' => 'material_2172', 'quantity' => 2]]],
                        ],
                        'brown' => [
                            ['rewards' => ['gold_200000', 'tokens_20', 'tp_100', 'back_2302'],        'requirement' => [['id' => 'material_2166', 'quantity' => 2], ['id' => 'material_2169', 'quantity' => 2], ['id' => 'material_2170', 'quantity' => 2]]],
                            ['rewards' => ['gold_200000', 'tokens_20', 'tp_100', 'material_2165:10'], 'requirement' => [['id' => 'material_2166', 'quantity' => 2], ['id' => 'material_2168', 'quantity' => 1], ['id' => 'material_2169', 'quantity' => 3], ['id' => 'material_2171', 'quantity' => 3], ['id' => 'material_2172', 'quantity' => 3]]],
                            ['rewards' => ['gold_200000', 'tokens_20', 'tp_100', 'wpn_1098'],         'requirement' => [['id' => 'material_2166', 'quantity' => 5], ['id' => 'material_2167', 'quantity' => 2], ['id' => 'material_2170', 'quantity' => 3], ['id' => 'material_2171', 'quantity' => 1], ['id' => 'material_2172', 'quantity' => 3]]],
                        ],
                        'rainbow' => [
                            ['rewards' => ['gold_3000000', 'tokens_150', 'tp_250', 'wpn_2316'], 'requirement' => [['id' => 'material_2166', 'quantity' => 20], ['id' => 'material_2167', 'quantity' => 20], ['id' => 'material_2168', 'quantity' => 20], ['id' => 'material_2169', 'quantity' => 20], ['id' => 'material_2170', 'quantity' => 20], ['id' => 'material_2171', 'quantity' => 20], ['id' => 'material_2172', 'quantity' => 20]]],
                        ],
                    ],
                    'set_package' => [
                        ['price' => 2000000, 'rewards' => ['hair_271_%s',  'set_1032_%s']],
                        ['price' => 4499,    'rewards' => ['hair_2278_%s', 'set_1028_%s', 'back_522', 'wpn_1099', 'pet_inarifox']],
                    ],
                    'wishing_tree'    => ['wpn_2321', 'back_2314', 'wpn_2322', 'back_2315', 'gold_', 'tokens_', 'tp_'],
                    'rewards_preview' => [
                        'set'    => ['set_2316_%s', 'set_2317_%s'],
                        'back'   => ['back_2310', 'back_2311'],
                        'hair'   => ['hair_2276_%s', 'hair_2277_%s'],
                        'skill'  => ['skill_2248', 'skill_2245'],
                        'weapon' => ['wpn_2317', 'wpn_2318'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2163', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2280_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2318_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2313',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2320',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Ramadhan 2025
            // -----------------------------------------------------------------
            [
                'title'       => 'Ramadhan 2025',
                'description' => 'Face the Ghost Spectre and earn exclusive Ramadhan rewards. Includes free outfit and pet for all players.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'RamadhanMenu',
                'active'      => false,
                'sort_order'  => 6,
                'data'        => [
                    'bosses' => [
                        'id'          => ['ene_2086'],
                        'xp'          => 'level * 2500 / 60',
                        'gold'        => 'level * 2500 / 60',
                        'name'        => 'Ghost Spectre',
                        'levels'      => [0, 5],
                        'rewards'     => [
                            ['damage' => '4k', 'reward' => 'material_2177'],
                            ['damage' => '8k', 'reward' => 'material_2178'],
                            ['damage' => '12k','reward' => 'material_2179'],
                        ],
                        'background'  => 'mission_1049',
                        'description' => '',
                    ],
                    'free_pet'    => 'pet_goat',
                    'free_outfit' => ['hair_2281_%s', 'set_2320_%s'],
                    'training' => [
                        ['id' => 'skill_2250', 'name' => 'Kinjutsu: Dragon Kareem',          'price' => [3999, 2499]],
                        ['id' => 'skill_2251', 'name' => 'Kinjutsu: Advanced Dragon Kareem', 'price' => [4999, 3499]],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2321_%s', 'set_2319_%s'],
                        'back'   => ['back_2317', 'back_2319', 'back_2316'],
                        'hair'   => ['hair_2282_%s', 'hair_2279_%s'],
                        'skill'  => ['skill_2252', 'skill_2253'],
                        'weapon' => ['wpn_2324', 'wpn_2323', 'wpn_2326'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',  'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_69',  'quantity' => 2,  'requirement' => 50],
                        ['id' => 'hair_2283_%s', 'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05', 'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2322_%s',  'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',   'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2318',    'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2325',     'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Easter 2025 (Four Sacred Beasts)
            // -----------------------------------------------------------------
            [
                'title'       => 'Easter 2025',
                'description' => 'The Four Sacred Beasts awaken. Defeat the Azure Dragon, Vermillion Bird, White Tiger, Black Turtle, and the mighty Kirin.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'EasterMenu',
                'active'      => false,
                'sort_order'  => 7,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2262', 'skill_2263', 'skill_2179', 'skill_2180', 'skill_2181',
                            'wpn_2334', 'wpn_2335', 'wpn_2336', 'wpn_2337', 'wpn_2231', 'wpn_2232',
                            'wpn_2233', 'back_2327', 'back_2328', 'back_2329', 'back_2330', 'back_2331',
                            'back_2216', 'back_2217', 'back_2218', 'essential_14', 'material_1001',
                            'essential_03', 'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2261', 'tokens_2000'],
                        'common' => [
                            'hair_2290_%s', 'hair_2291_%s', 'hair_2292_%s', 'hair_2293_%s',
                            'hair_2294_%s', 'hair_2295_%s', 'hair_2296_%s', 'hair_2297_%s',
                            'set_2330_%s', 'set_2331_%s', 'set_2332_%s', 'set_2333_%s',
                            'set_2334_%s', 'set_2335_%s', 'set_2336_%s', 'set_2337_%s',
                            'material_2180', 'material_2181', 'material_2182', 'material_2183',
                            'material_2184', 'material_2185', 'material_2186',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2186', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2287_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2327_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2324',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2331',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    'bosses' => [
                        ['id' => ['ene_517'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Black Turtle',    'levels' => [0, 5], 'rewards' => ['material_2182', 'material_2180', 'material_2186'], 'background' => 'mission_1052', 'description' => ''],
                        ['id' => ['ene_514'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Azure Dragon',    'levels' => [0, 5], 'rewards' => ['material_2181', 'material_2180', 'material_2186'], 'background' => 'mission_1050', 'description' => ''],
                        ['id' => ['ene_516'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'White Tiger',     'levels' => [0, 5], 'rewards' => ['material_2183', 'material_2180', 'material_2186'], 'background' => 'mission_1053', 'description' => ''],
                        ['id' => ['ene_515'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Vermillion Bird', 'levels' => [0, 5], 'rewards' => ['material_2184', 'material_2180', 'material_2186'], 'background' => 'mission_1051', 'description' => ''],
                        ['id' => ['ene_518'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Kirin',           'levels' => [0, 5], 'rewards' => ['material_2185', 'material_2180', 'material_2186', 'wpn_1285'], 'background' => 'mission_1054', 'description' => ''],
                    ],
                    'training_kirin' => [
                        ['id' => 'skill_2259', 'name' => 'Kinjutsu: Kirin Dash',      'price' => [1999, 1499]],
                        ['id' => 'skill_2260', 'name' => 'Kinjutsu: Twin Kirin Dash', 'price' => [1499, 999]],
                    ],
                    'training_bewitch' => [
                        ['id' => 'skill_385', 'name' => 'Kinjutsu: Bewitching Eyes Illusion',               'price' => [1999, 1499]],
                        ['id' => 'skill_386', 'name' => 'Kinjutsu: Dark Demonic Illusion Bewitching Eyes', 'price' => [1499, 999]],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2325_%s', 'set_2326_%s', 'set_2328_%s', 'set_2329_%s'],
                        'back'   => ['back_2322', 'back_2323', 'back_2325', 'back_2326'],
                        'hair'   => ['hair_2285_%s', 'hair_2286_%s', 'hair_2288_%s', 'hair_2289_%s'],
                        'skill'  => ['skill_2255', 'skill_2256', 'skill_2257', 'skill_2258'],
                        'weapon' => ['wpn_2329', 'wpn_2330', 'wpn_2332', 'wpn_2333'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2180', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2284_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2324_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2321',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2328',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // World Martial Games 2025
            // -----------------------------------------------------------------
            [
                'title'       => 'World Martial Games 2025',
                'description' => 'The World Martial Games begins. Face Grand Master Titan, Abbys Leviathan, and Wrestling Gorilla in this epic tournament event.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'WMGMenu',
                'active'      => false,
                'sort_order'  => 8,
                'data'        => [
                    'bosses' => [
                        ['id' => ['ene_2090'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Grand Master Titan', 'levels' => [0, 5], 'rewards' => ['material_2187', 'material_2191'], 'background' => 'mission_1055', 'description' => ''],
                        ['id' => ['ene_2088'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Abbys Leviathan',   'levels' => [0, 5], 'rewards' => ['material_2189', 'material_2191'], 'background' => 'mission_1055', 'description' => ''],
                        ['id' => ['ene_2089'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Wrestling Gorilla', 'levels' => [0, 5], 'rewards' => ['material_2188', 'material_2191'], 'background' => 'mission_1055', 'description' => ''],
                    ],
                    // rewards_preview is a flat array for this event
                    'rewards_preview'  => ['skill_2274', 'wpn_2339', 'back_2333', 'hair_2299_%s', 'set_2339_%s', 'skill_2267', 'wpn_2340', 'back_2334', 'hair_2300_%s', 'set_2340_%s', 'skill_2268'],
                    'minigame_rewards' => 'material_2190',
                    'milestone_battle' => [
                        ['id' => 'gold_100000',  'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_69',  'quantity' => 2,  'requirement' => 50],
                        ['id' => 'hair_2301_%s', 'quantity' => 1,  'requirement' => 100],
                        ['id' => 'set_2341_%s',  'quantity' => 1,  'requirement' => 200],
                        ['id' => 'tokens_150',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'back_2335',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'wpn_2341',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'skill_2269',   'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Summer 2025
            // -----------------------------------------------------------------
            [
                'title'       => 'Summer 2025',
                'description' => 'Rodan, Mothra, and Godzilla bring chaos to the summer. Gather materials and claim exclusive summer rewards.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'SummerMenu',
                'active'      => false,
                'sort_order'  => 9,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2276', 'skill_2277', 'skill_2196', 'skill_2197', 'skill_2198',
                            'wpn_2347', 'wpn_2348', 'wpn_2349', 'wpn_2350', 'wpn_2247', 'wpn_2248',
                            'wpn_2249', 'back_2342', 'back_2343', 'back_2344', 'back_2345', 'back_2346',
                            'back_2233', 'back_2234', 'back_2235', 'material_1001', 'essential_03',
                            'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2275', 'tokens_2000'],
                        'common' => [
                            'hair_2307_%s', 'hair_2308_%s', 'hair_2309_%s', 'hair_2310_%s',
                            'hair_2311_%s', 'hair_2312_%s', 'hair_2313_%s', 'hair_2314_%s',
                            'set_2347_%s', 'set_2348_%s', 'set_2349_%s', 'set_2350_%s',
                            'set_2351_%s', 'set_2352_%s', 'set_2353_%s', 'set_2354_%s',
                            'material_2192', 'material_2193', 'material_2195', 'material_2196',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2195', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2306_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2346_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2341',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2346',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    'bosses' => [
                        ['id' => ['ene_2092'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Rodan',   'levels' => [0, 5], 'rewards' => ['material_2193', 'material_2196', 'material_2195'], 'background' => 'mission_1056', 'description' => 'Rodan is a giant flying reptile monster that is known for its destructive power and ability to breathe fire.'],
                        ['id' => ['ene_2093'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Mothra',  'levels' => [0, 5], 'rewards' => ['material_2192', 'material_2196', 'material_2195'], 'background' => 'mission_1056', 'description' => 'Mothra is a giant moth-like monster that is known for its destructive power with powerful wind gusts from her wings and can summon energy blasts.'],
                        ['id' => ['ene_2094'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Godzilla','levels' => [0, 5], 'rewards' => ['material_2194', 'material_2196', 'material_2195'], 'background' => 'mission_1056', 'description' => 'Godzilla is a giant monster that is known for its giant size by absorbing energies. Capable of doing devastating atomic breath and immense physical power.'],
                    ],
                    'training' => [
                        ['id' => 'skill_2280', 'name' => 'Kinjutsu: Aqua Reign',        'price' => [3000, 3000]],
                        ['id' => 'skill_2281', 'name' => 'Kinjutsu: Aqua Dragon Reign', 'price' => [1500, 1000]],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2355_%s'],
                        'back'   => ['back_2347', 'back_2348'],
                        'hair'   => ['hair_2315_%s'],
                        'skill'  => ['skill_2278', 'skill_2279'],
                        'weapon' => ['wpn_2351', 'wpn_2352'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2194', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2305_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2345_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2340',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2345',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Independence Day 2025 (Indonesian)
            // -----------------------------------------------------------------
            [
                'title'       => 'Independence Day 2025',
                'description' => 'Indonesian mythical creatures rise to defend the homeland. Face Ahool, Sembrani, Lembuswana, Besukih, and the fearsome Leak.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'IndependenceMenu',
                'active'      => false,
                'sort_order'  => 10,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2287', 'skill_2288', 'skill_2206', 'skill_2207', 'skill_2208',
                            'wpn_2360', 'wpn_2361', 'wpn_2362', 'wpn_2363', 'wpn_2258', 'wpn_2259',
                            'wpn_2261', 'back_2355', 'back_2356', 'back_2357', 'back_2358', 'back_2359',
                            'back_2245', 'back_2246', 'back_2247', 'material_1001', 'essential_03',
                            'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2286', 'tokens_2000'],
                        'common' => [
                            'hair_2321_%s', 'hair_2322_%s', 'hair_2323_%s', 'hair_2324_%s',
                            'hair_2325_%s', 'hair_2326_%s', 'hair_2327_%s', 'hair_2328_%s',
                            'set_2361_%s', 'set_2362_%s', 'set_2363_%s', 'set_2364_%s',
                            'set_2365_%s', 'set_2366_%s', 'set_2367_%s', 'set_2368_%s',
                            'material_2200', 'material_2201', 'material_2202', 'material_2203', 'material_2197',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2197', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2320_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2360_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2354',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2358',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    'bosses' => [
                        ['id' => ['ene_2098'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Ahool',       'levels' => [0, 5], 'rewards' => ['material_2203', 'material_2198', 'material_2197'], 'background' => 'mission_1057', 'description' => 'Ahool is a fierce, bat-like creature. It has a large, leathery body, wide wings, and a gaping mouth with sharp teeth.'],
                        ['id' => ['ene_2099'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Sembrani',    'levels' => [0, 5], 'rewards' => ['material_2202', 'material_2198', 'material_2197'], 'background' => 'mission_1057', 'description' => 'Sembrani is a fiery, horse-like creature. Its body is covered in sleek, white fur, but flames erupt from its mane.'],
                        ['id' => ['ene_2095'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Lembuswana', 'levels' => [0, 5], 'rewards' => ['material_2200', 'material_2198', 'material_2197'], 'background' => 'mission_1057', 'description' => 'Lembuswana is a Horned beast-warrior with black feather and a golden-trimmed trident.'],
                        ['id' => ['ene_2096'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Besukih',    'levels' => [0, 5], 'rewards' => ['material_2201', 'material_2198', 'material_2197'], 'background' => 'mission_1057', 'description' => 'A majestic green dragon with golden ornaments and flowing azure clouds along its body.'],
                        ['id' => ['ene_2097'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Leak',       'levels' => [0, 5], 'rewards' => ['material_2199', 'material_2198', 'material_2197'], 'background' => 'mission_1057', 'description' => 'A floating demonic head with tusks, a crown of red jewels, and wild hair. It is feared as a harbinger of misfortune.'],
                    ],
                    'spending' => [
                        ['id' => 'essential_01',  'quantity' => 3,  'requirement' => 10],
                        ['id' => 'tp_200',        'quantity' => 1,  'requirement' => 25],
                        ['id' => 'material_1001', 'quantity' => 1,  'requirement' => 50],
                        ['id' => 'material_69',   'quantity' => 25, 'requirement' => 100],
                        ['id' => 'tp_250',        'quantity' => 1,  'requirement' => 250],
                        ['id' => 'ss_500',        'quantity' => 1,  'requirement' => 500],
                        ['id' => 'hair_2319_%s',  'quantity' => 1,  'requirement' => 1000],
                        ['id' => 'set_2359_%s',   'quantity' => 1,  'requirement' => 2500],
                        ['id' => 'pet_leak',      'quantity' => 1,  'requirement' => 5000],
                        ['id' => 'back_2353',     'quantity' => 1,  'requirement' => 10000],
                        ['id' => 'accessory_2120','quantity' => 1,  'requirement' => 20000],
                        ['id' => 'wpn_2357',      'quantity' => 1,  'requirement' => 30000],
                        ['id' => 'skill_2284',    'quantity' => 1,  'requirement' => 50000],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2357_%s', 'set_2358_%s'],
                        'back'   => ['back_2351', 'back_2352'],
                        'hair'   => ['hair_2317_%s', 'hair_2318_%s'],
                        'skill'  => ['skill_2282', 'skill_2283'],
                        'weapon' => ['wpn_2355', 'wpn_2356'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2199', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2316_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2356_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2350',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2354',      'quantity' => 1,  'requirement' => 750],
                    ],
                    'global_milestone_battle' => [
                        ['id' => 'tp_350',     'quantity' => 1, 'requirement' => 10000],
                        ['id' => 'back_2349',  'quantity' => 1, 'requirement' => 25000],
                        ['id' => 'tokens_500', 'quantity' => 1, 'requirement' => 50000],
                        ['id' => 'wpn_2353',   'quantity' => 1, 'requirement' => 100000],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Yin Yang 2025
            // Unique: has separate milestone_battle_yin and milestone_battle_yang
            // -----------------------------------------------------------------
            [
                'title'       => 'Yin Yang 2025',
                'description' => 'The balance of Yin and Yang is broken. Face the White Dragon Yang and Black Tiger Yin to restore harmony.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'YinYangMenu',
                'active'      => false,
                'sort_order'  => 11,
                'data'        => [
                    'bosses' => [
                        ['id' => ['ene_2100'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'White Dragon Yang', 'levels' => [0, 5], 'rewards' => ['material_2204', 'material_2205', 'material_2208'], 'background' => 'mission_1058', 'description' => ''],
                        ['id' => ['ene_2101'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Black Tiger Yin',   'levels' => [0, 5], 'rewards' => ['material_2206', 'material_2207', 'material_2208'], 'background' => 'mission_1059', 'description' => ''],
                    ],
                    'training' => [
                        ['id' => 'skill_2292', 'name' => 'Kinjutsu: Sentinel Sense',      'price' => [5999, 4999]],
                        ['id' => 'skill_2293', 'name' => 'Kinjutsu: Sentinel Soul Sense', 'price' => [3499, 1999]],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2374_%s', 'set_2375_%s'],
                        'back'   => ['back_2365', 'back_2366'],
                        'hair'   => ['hair_2334_%s', 'hair_2335_%s'],
                        'skill'  => ['skill_2296', 'skill_2297'],
                        'weapon' => ['wpn_2369', 'wpn_2370'],
                    ],
                    'milestone_battle_yin' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2207', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2332_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2372_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2363',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2367',      'quantity' => 1,  'requirement' => 750],
                    ],
                    'milestone_battle_yang' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2205', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2333_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2373_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2364',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2368',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
            // -----------------------------------------------------------------
            // Halloween 2025
            // -----------------------------------------------------------------
            [
                'title'       => 'Halloween 2025',
                'description' => 'The Cursed Pumpkin King and his undead army march. Defeat pumpkin minions, skeleton ninjas, zombie samurai, and the Headless Horseman.',
                'date'        => null,
                'image_url'   => '',
                'panel'       => 'HalloweenMenu',
                'active'      => false,
                'sort_order'  => 12,
                'data'        => [
                    'gacha' => [
                        'mid' => [
                            'skill_2309', 'skill_2310', 'skill_2214', 'skill_2215', 'skill_2216',
                            'wpn_2380', 'wpn_2381', 'wpn_2382', 'wpn_2383', 'wpn_2275', 'wpn_2276',
                            'wpn_2277', 'back_2378', 'back_2379', 'back_2380', 'back_2381', 'back_2382',
                            'back_2262', 'back_2263', 'back_2264', 'material_1001', 'essential_03',
                            'essential_04', 'essential_05', 'tokens_',
                        ],
                        'top'    => ['skill_2308', 'tokens_2000'],
                        'common' => [
                            'hair_2344_%s', 'hair_2345_%s', 'hair_2346_%s', 'hair_2347_%s',
                            'hair_2219_%s', 'hair_2220_%s', 'hair_2221_%s', 'hair_2222_%s',
                            'set_2384_%s', 'set_2385_%s', 'set_2386_%s', 'set_2387_%s',
                            'set_2258_%s', 'set_2259_%s', 'set_2260_%s', 'set_2261_%s',
                            'material_2215', 'material_2209', 'material_2210', 'material_2211',
                            'material_2213', 'material_2214',
                            'item_33', 'item_34', 'item_35', 'item_36', 'item_40', 'item_39',
                            'item_38', 'item_37', 'item_44', 'item_43', 'item_42', 'item_41',
                            'item_24', 'item_32', 'item_31', 'item_30', 'item_29', 'item_28',
                            'item_27', 'item_26', 'item_25', 'item_23', 'item_22', 'item_21',
                            'item_20', 'item_19', 'item_18', 'item_17', 'item_16', 'item_15',
                            'item_14', 'item_13', 'item_12', 'item_11', 'item_10', 'item_09',
                            'item_08', 'item_07', 'item_06', 'item_05', 'item_04', 'item_03',
                            'item_02', 'gold_',
                        ],
                        'milestone' => [
                            ['id' => 'material_2215', 'quantity' => 5,  'requirement' => 10],
                            ['id' => 'hair_2343_%s',  'quantity' => 1,  'requirement' => 50],
                            ['id' => 'material_69',   'quantity' => 5,  'requirement' => 100],
                            ['id' => 'set_2383_%s',   'quantity' => 1,  'requirement' => 200],
                            ['id' => 'material_941',  'quantity' => 10, 'requirement' => 300],
                            ['id' => 'tokens_200',    'quantity' => 1,  'requirement' => 400],
                            ['id' => 'back_2377',     'quantity' => 1,  'requirement' => 600],
                            ['id' => 'wpn_2379',      'quantity' => 1,  'requirement' => 750],
                        ],
                    ],
                    'bosses' => [
                        ['id' => ['ene_2104'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Pumpkin Minion',           'levels' => [0, 5], 'rewards' => ['material_2213', 'material_2209', 'material_2215'], 'background' => 'mission_1057', 'description' => 'None.'],
                        ['id' => ['ene_2105'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Skeleton Ninja',           'levels' => [0, 5], 'rewards' => ['material_2211', 'material_2209', 'material_2215'], 'background' => 'mission_1057', 'description' => 'None.'],
                        ['id' => ['ene_2106'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Zombie Samurai',           'levels' => [0, 5], 'rewards' => ['material_2210', 'material_2209', 'material_2215'], 'background' => 'mission_1057', 'description' => 'None.'],
                        ['id' => ['ene_2103'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Headless Pumpkin Horseman','levels' => [0, 5], 'rewards' => ['material_2214', 'material_2209', 'material_2215'], 'background' => 'mission_1057', 'description' => 'None.'],
                        ['id' => ['ene_2102'], 'xp' => 'level * 2500 / 60', 'gold' => 'level * 2500 / 60', 'name' => 'Cursed Pumpkin King',      'levels' => [0, 5], 'rewards' => ['material_2212', 'material_2209', 'material_2215'], 'background' => 'mission_1057', 'description' => 'None.'],
                    ],
                    'training' => [
                        ['id' => 'skill_2303', 'name' => 'Kinjutsu: Phantom Harvest', 'price' => [4999, 3999]],
                    ],
                    'rewards_preview' => [
                        'set'    => ['set_2379_%s', 'set_2380_%s', 'set_2381_%s'],
                        'back'   => ['back_2372', 'back_2373', 'back_2374', 'back_2375'],
                        'hair'   => ['hair_2339_%s', 'hair_2340_%s', 'hair_2341_%s'],
                        'skill'  => ['skill_2304', 'skill_2305', 'skill_2306', 'skill_2307'],
                        'weapon' => ['wpn_2374', 'wpn_2375', 'wpn_2376', 'wpn_2377'],
                    ],
                    'milestone_battle' => [
                        ['id' => 'gold_100000',   'quantity' => 1,  'requirement' => 10],
                        ['id' => 'material_2212', 'quantity' => 10, 'requirement' => 50],
                        ['id' => 'hair_2342_%s',  'quantity' => 1,  'requirement' => 100],
                        ['id' => 'essential_05',  'quantity' => 5,  'requirement' => 200],
                        ['id' => 'set_2382_%s',   'quantity' => 1,  'requirement' => 300],
                        ['id' => 'tokens_150',    'quantity' => 1,  'requirement' => 400],
                        ['id' => 'back_2376',     'quantity' => 1,  'requirement' => 600],
                        ['id' => 'wpn_2378',      'quantity' => 1,  'requirement' => 750],
                    ],
                ],
            ],
        ];

        // ----------------------------------------------------------------
        // PERMANENT EVENTS  (type = 'permanent')
        // ----------------------------------------------------------------
        $permanent = [
            [
                'title'      => 'Monster Hunter',
                'icon'       => 'monsterhunter',
                'panel'      => 'MonsterHunter',
                'sort_order' => 1,
                'data'       => [
                    // The boss the player fights — must match an enemy ID in EnemyInfo.
                    'boss_id'     => 'ene_2000',
                    'energy_max'  => 100,
                    'energy_cost' => 20,
                    'end'         => '',      // e.g. "Mar 31, 2026"
                    // Rewards granted on every win. Uses RewardGrantService format.
                    'rewards'     => [
                        'material_mh_1:3',
                        'gold_10000',
                        'xp_percent_5',
                    ],
                ],
            ],
            [
                'title'      => 'Dragon Hunt',
                'icon'       => 'dragonhunt',
                'panel'      => 'DragonHunt',
                'sort_order' => 2,
                'data'       => [
                    'material_token_cost'   => 10,      // tokens per material purchase
                    'normal_mode_gold_cost' => 250000,  // gold cost for Normal mode
                    'easy_mode_token_cost'  => 100,     // token cost for Easy mode
                    // HP% capture windows: [start, end] per mode index (0=Hard,1=Normal,2=Easy)
                    'capture_range'  => ['0' => [0, 5], '1' => [0, 15], '2' => [0, 25]],
                    // Per-boss rewards keyed by enemy ID (boss IDs from client GameData).
                    'rewards_per_boss' => [],
                    'rewards_default'  => ['gold_10000'],
                ],
            ],
            [
                'title'      => 'Justice Badge',
                'icon'       => 'justicebadge',
                'panel'      => 'JusticeBadge',
                'sort_order' => 3,
                'data'       => [
                    'end'     => '',  // e.g. "Mar 31, 2026"
                    // Rewards matched by the requirement (badge count) the client sends.
                    // IDs must match what the client GameData shows for each tier.
                    'rewards' => [
                        ['id' => 'xp_percent_100', 'requirement' => 5],
                        ['id' => 'gold_50000',      'requirement' => 10],
                        ['id' => 'tp_100',          'requirement' => 15],
                        ['id' => 'ss_50',           'requirement' => 20],
                    ],
                ],
            ],
        ];

        // ----------------------------------------------------------------
        // FEATURES  (type = 'feature')
        // ----------------------------------------------------------------
        $features = [
            ['title' => 'Giveaway Center', 'icon' => 'giveaway',    'panel' => 'GiveawayCenter', 'sort_order' => 1],
            ['title' => 'Leaderboard',     'icon' => 'leaderboard', 'panel' => 'Leaderboard',    'sort_order' => 2],
            ['title' => 'Tailed Beast',    'icon' => 'tailedbeast', 'panel' => 'TailedBeast',    'sort_order' => 3, 'inside' => true],
            [
                'title'      => 'Daily Gacha',
                'icon'       => 'dailygacha',
                'panel'      => 'DailyGacha',
                'sort_order' => 4,
                'data'       => [
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
                    'bonus_rewards' => [
                        ['id' => 'tokens_100',     'req' => 5],
                        ['id' => 'tokens_250',     'req' => 10],
                        ['id' => 'material_205',   'req' => 25],
                        ['id' => 'tokens_500',     'req' => 50],
                        ['id' => 'material_874:5', 'req' => 75],
                        ['id' => 'tokens_1000',    'req' => 100],
                        ['id' => 'material_205:3', 'req' => 150],
                        ['id' => 'tokens_2000',    'req' => 200],
                    ],
                ],
            ],
            [
                'title'      => 'Dragon Gacha',
                'icon'       => 'dragongacha',
                'panel'      => 'DragonGacha',
                'sort_order' => 5,
                'data'       => [
                    // Weighted tier selection (must sum to 100 for clarity)
                    'pool_weights' => [5, 25, 70],    // [top%, mid%, common%]
                    // Cost per draw type (mirrors client PRICE_COINS / PRICE_TOKENS constants)
                    // 'qty' is the number of rolls performed per draw.
                    'draws'        => [
                        'normal'         => ['qty' => 1, 'coin_cost' => 1,  'token_cost' => 25],
                        'advanced'       => ['qty' => 2, 'coin_cost' => 2,  'token_cost' => 50],
                        'advanced_bonus' => ['qty' => 6,                    'token_cost' => 250],
                    ],
                    // Reward pools — replace placeholder IDs with real item/skill IDs
                    'pool'         => [
                        'top'    => ['material_773:5'],
                        'mid'    => ['material_773:3'],
                        'common' => ['material_773:1'],
                    ],
                ],
            ],
            ['title' => 'Exotic Package',  'icon' => 'exotic',      'panel' => 'ExoticPackage',  'sort_order' => 6],
        ];

        // ----------------------------------------------------------------
        // PACKAGE  (type = 'package')
        // The full tier content lives in data->content.
        // ----------------------------------------------------------------
        $packages = [
            [
                'title'      => 'Elemental Ars Package',
                'date'       => '15/04 - 05/03, 2026',
                'image_url'  => '',
                'sort_order' => 1,
                'data'       => [
                    'content' => [
                        [
                            'name'    => 'Codex Elementia',
                            'price'   => 'IDR. 100,000',
                            'outfits' => [
                                'ani'       => [null],
                                'pet'       => [null],
                                'set'       => ['set_2402_%s'],
                                'back'      => ['back_2396'],
                                'hair'      => ['hair_2361_%s'],
                                'skill'     => [null],
                                'weapon'    => [null],
                                'accessory' => [null],
                            ],
                            'rewards' => ['hair_2361_%s', 'back_2396', 'set_2402_%s', 'emblem', 'tokens_4500'],
                        ],
                        [
                            'name'    => 'Grimoire Arcanum',
                            'price'   => 'IDR. 250,000',
                            'outfits' => [
                                'ani'       => [null],
                                'pet'       => [null],
                                'set'       => ['set_2402_%s', 'set_2401_%s'],
                                'back'      => ['back_2396'],
                                'hair'      => ['hair_2361_%s'],
                                'skill'     => ['skill_2323'],
                                'weapon'    => [null],
                                'accessory' => [null],
                            ],
                            'rewards' => [
                                'hair_2361_%s', 'set_2402_%s', 'set_2401_%s', 'back_2396',
                                'skill_2323', 'emblem', 'tokens_10250',
                            ],
                        ],
                        [
                            'name'    => 'Elementis Corcondia',
                            'price'   => 'IDR. 500,000',
                            'outfits' => [
                                'ani'       => [null],
                                'pet'       => ['pet_ancientgolem'],
                                'set'       => ['set_2402_%s', 'set_2401_%s'],
                                'back'      => ['back_2396'],
                                'hair'      => ['hair_2361_%s'],
                                'skill'     => ['skill_2323', 'skill_2324'],
                                'weapon'    => ['wpn_2399'],
                                'accessory' => [null],
                            ],
                            'rewards' => [
                                'hair_2361_%s', 'set_2402_%s', 'set_2401_%s', 'back_2396',
                                'wpn_2399', 'pet_ancientgolem', 'skill_2323', 'skill_2324',
                                'emblem', 'tokens_21000',
                            ],
                        ],
                        [
                            'name'    => 'Tomea Astralis',
                            'price'   => 'IDR. 1,000,000',
                            'outfits' => [
                                'ani'       => [null],
                                'pet'       => ['pet_ancientgolem'],
                                'set'       => ['set_2402_%s', 'set_2401_%s'],
                                'back'      => ['back_2396'],
                                'hair'      => ['hair_2361_%s'],
                                'skill'     => ['skill_2323', 'skill_2324', 'skill_2325'],
                                'weapon'    => ['wpn_2399'],
                                'accessory' => [null],
                            ],
                            'rewards' => [
                                'hair_2361_%s', 'set_2402_%s', 'set_2401_%s', 'back_2396',
                                'wpn_2399', 'pet_ancientgolem', 'skill_2323', 'skill_2324',
                                'skill_2325', 'emblem', 'tokens_45000',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // ----------------------------------------------------------------
        // Insert everything
        // ----------------------------------------------------------------
        foreach ($seasonal as $row) {
            GameEvent::create(array_merge(['type' => 'seasonal', 'active' => true], $row));
        }

        foreach ($permanent as $row) {
            GameEvent::create(array_merge(['type' => 'permanent', 'active' => true, 'image_url' => ''], $row));
        }

        foreach ($features as $row) {
            GameEvent::create(array_merge(['type' => 'feature', 'active' => true, 'image_url' => ''], $row));
        }

        foreach ($packages as $row) {
            GameEvent::create(array_merge(['type' => 'package', 'active' => true], $row));
        }
    }
}