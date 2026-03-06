<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameConfig;

class EudemonGardenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bosses = [
            [
                'id' => 'boss_01',
                'name' => 'Kamaitachi',
                'lvl' => 10,
                'grade' => 'C',
                'xp' => 3000,
                'gold' => 5000,
                'rewards' => [
                    ['id' => 'material_01', 'rate' => 100, 'min' => 1, 'max' => 1],
                    ['id' => 'material_01', 'rate' => 20, 'min' => 1, 'max' => 2],
                    ['id' => 'material_2110', 'rate' => 5, 'min' => 1, 'max' => 1],
                    ['id' => 'item_58', 'rate' => 5, 'min' => 1, 'max' => 1],
                    ['id' => 'wpn_1138', 'rate' => 5, 'min' => 1, 'max' => 1, 'unique' => true, 'owned_rate' => 0.05],
                ]
            ],
            [
                'id' => 'boss_02',
                'name' => 'Hell Horse',
                'lvl' => 20,
                'grade' => 'C',
                'xp' => 6000,
                'gold' => 10000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_c', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_03',
                'name' => 'Kabutomushi Musha',
                'lvl' => 25,
                'grade' => 'B',
                'xp' => 8000,
                'gold' => 12500,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_b', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_04',
                'name' => 'Kinkaku & Ginkaku',
                'lvl' => 30,
                'grade' => 'B',
                'xp' => 10000,
                'gold' => 15000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_b', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_05',
                'name' => 'Thunder Eagle',
                'lvl' => 40,
                'grade' => 'A',
                'xp' => 15000,
                'gold' => 20000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_a', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_06',
                'name' => 'Mammoth King',
                'lvl' => 50,
                'grade' => 'A',
                'xp' => 20000,
                'gold' => 25000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_a', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_07',
                'name' => 'Ocean Queen',
                'lvl' => 55,
                'grade' => 'S',
                'xp' => 25000,
                'gold' => 30000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_s', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_08',
                'name' => 'Ghost Soldier',
                'lvl' => 60,
                'grade' => 'S',
                'xp' => 30000,
                'gold' => 35000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_s', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_09',
                'name' => 'Battle Angel',
                'lvl' => 70,
                'grade' => 'S',
                'xp' => 40000,
                'gold' => 40000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_s', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_10',
                'name' => 'Infernal Chimera',
                'lvl' => 80,
                'grade' => 'S',
                'xp' => 50000,
                'gold' => 50000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_s', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
            [
                'id' => 'boss_11',
                'name' => 'Taowu & Taotie',
                'lvl' => 90,
                'grade' => 'S',
                'xp' => 70000,
                'gold' => 75000,
                'rewards' => [
                    ['id' => 'item_eudemon_shard_s', 'rate' => 100, 'min' => 1, 'max' => 1]
                ]
            ],
        ];

        GameConfig::set('eudemon', ['bosses' => $bosses]);
    }
}
