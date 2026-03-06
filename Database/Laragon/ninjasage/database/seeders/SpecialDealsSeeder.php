<?php

namespace Database\Seeders;

use App\Models\GameConfig;
use Illuminate\Database\Seeder;

/**
 * Seeds the "special_deals" GameConfig entry.
 *
 * Each deal:
 *   id    – unique integer used as the purchase key
 *   name  – display name shown in the panel
 *   end   – end-date / availability text shown below the name
 *   price – token cost
 *   items – reward item IDs; use "item_id:qty" for quantities > 1
 *
 * Re-run with:  php artisan db:seed --class=SpecialDealsSeeder
 */
class SpecialDealsSeeder extends Seeder
{
    public function run(): void
    {
        GameConfig::set('special_deals', [
            [
                'id'    => 1,
                'name'  => 'Starter Pack',
                'end'   => 'Limited Time',
                'price' => 50,
                'items' => [
                    'material_509:10',
                    'essential_01:1',
                ],
            ],
            [
                'id'    => 2,
                'name'  => 'Warrior Bundle',
                'end'   => 'Limited Time',
                'price' => 100,
                'items' => [
                    'wpn_81',
                    'material_509:20',
                    'essential_03:1',
                ],
            ],
            [
                'id'    => 3,
                'name'  => 'Material Cache',
                'end'   => 'Limited Time',
                'price' => 75,
                'items' => [
                    'material_509:30',
                    'material_01:5',
                    'material_02:3',
                ],
            ],
            [
                'id'    => 4,
                'name'  => 'Elite Pack',
                'end'   => 'Limited Time',
                'price' => 200,
                'items' => [
                    'wpn_82',
                    'material_509:50',
                    'essential_01:2',
                    'essential_03:1',
                ],
            ],
        ]);

        $this->command->info('Special deals seeded into game_configs.');
    }
}
