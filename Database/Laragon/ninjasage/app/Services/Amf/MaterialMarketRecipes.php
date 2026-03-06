<?php

/**
 * Material Market forge recipes — managed via the admin panel (MaterialMarketManager).
 *
 * Format:
 *   'output_item_id' => [
 *       'materials' => ['material_id_1', ...],
 *       'qty'       => [5, ...],
 *       'end'       => 'Available', // null = shown as Unavailable in client
 *   ]
 *
 * Item-ID prefix determines the client tab:
 *   wpn_ → Weapon   set_ → Set   back_ → Back   accessory_ → Accessory
 *   hair_ → Hair     skill_ → Skill   pet_ → Pet   material_ → Material
 */
return [

    // ── Weapon ──
    'wpn_609' => [
        'materials' => ['material_01'],
        'qty'       => [1],
        'end'       => 'Available',
    ],
    'wpn_119' => [
        'materials' => ['material_01'],
        'qty'       => [5],
        'end'       => 'Available',
    ],
    'wpn_179' => [
        'materials' => ['material_01'],
        'qty'       => [2],
        'end'       => 'Available',
    ],
    'wpn_164' => [
        'materials' => ['material_01'],
        'qty'       => [3],
        'end'       => 'Available',
    ],
    'wpn_293' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [3, 1],
        'end'       => 'Available',
    ],
    'wpn_137' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [5, 1],
        'end'       => 'Available',
    ],
    'wpn_135' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [3, 1],
        'end'       => 'Available',
    ],
    'wpn_294' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [5, 1],
        'end'       => 'Available',
    ],
    'wpn_295' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [5, 2],
        'end'       => 'Available',
    ],
    'wpn_131' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [2, 1],
        'end'       => 'Available',
    ],
    'wpn_125' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [5, 3],
        'end'       => 'Available',
    ],
    'wpn_181' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [3, 1],
        'end'       => 'Available',
    ],
    'wpn_296' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [7, 2],
        'end'       => 'Available',
    ],
    'wpn_140' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [6, 3],
        'end'       => 'Available',
    ],
    'wpn_297' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [8, 3],
        'end'       => 'Available',
    ],
    'wpn_303' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [5, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_136' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [5, 1, 1],
        'end'       => 'Available',
    ],
    'wpn_139' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [3, 2, 1],
        'end'       => 'Available',
    ],
    'wpn_138' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [3, 2, 1],
        'end'       => 'Available',
    ],
    'wpn_126' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [5, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_134' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [4, 4, 1],
        'end'       => 'Available',
    ],
    'wpn_298' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [6, 3, 2],
        'end'       => 'Available',
    ],
    'wpn_142' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [5, 2, 2],
        'end'       => 'Available',
    ],
    'wpn_127' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [5, 3, 2],
        'end'       => 'Available',
    ],
    'wpn_144' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [5, 4, 2],
        'end'       => 'Available',
    ],
    'wpn_611' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [4, 3, 2, 1],
        'end'       => 'Available',
    ],
    'wpn_307' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [5, 3, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_145' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [4, 3, 2, 1],
        'end'       => 'Available',
    ],
    'wpn_306' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [5, 3, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_299' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [5, 3, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_146' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [4, 3, 2, 1],
        'end'       => 'Available',
    ],
    'wpn_128' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [8, 5, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_308' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [5, 5, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_291' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [8, 5, 3, 2],
        'end'       => 'Available',
    ],
    'wpn_130' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [10, 6, 4, 3],
        'end'       => 'Available',
    ],
    'wpn_148' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [3, 2, 2, 1],
        'end'       => 'Available',
    ],
    'wpn_129' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [10, 8, 6, 3, 1],
        'end'       => 'Available',
    ],
    'wpn_292' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [9, 6, 4, 5, 1],
        'end'       => 'Available',
    ],
    'wpn_150' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [4, 3, 1, 1, 1],
        'end'       => 'Available',
    ],
    'wpn_612' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [4, 3, 1, 1, 1],
        'end'       => 'Available',
    ],
    'wpn_151' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [4, 3, 1, 1, 1],
        'end'       => 'Available',
    ],
    'wpn_336' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [5, 4, 2, 1, 2],
        'end'       => 'Available',
    ],
    'wpn_339' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [5, 4, 2, 1, 2],
        'end'       => 'Available',
    ],
    'wpn_332' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [10, 7, 5, 4, 3],
        'end'       => 'Available',
    ],
    'wpn_1139' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [3, 2],
        'end'       => 'Available',
    ],
    'wpn_1141' => [
        'materials' => ['material_01', 'material_02'],
        'qty'       => [5, 4],
        'end'       => 'Available',
    ],
    'wpn_1143' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [7, 5, 3],
        'end'       => 'Available',
    ],
    'wpn_1145' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [15, 13, 7],
        'end'       => 'Available',
    ],
    'wpn_1147' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [15, 13, 7],
        'end'       => 'Available',
    ],
    'wpn_1149' => [
        'materials' => ['material_01', 'material_02', 'material_03'],
        'qty'       => [24, 17, 12],
        'end'       => 'Available',
    ],
    'wpn_1151' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [30, 20, 15, 6],
        'end'       => 'Available',
    ],
    'wpn_1153' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04'],
        'qty'       => [35, 27, 23, 15],
        'end'       => 'Available',
    ],
    'wpn_1155' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [40, 33, 25, 15, 3],
        'end'       => 'Available',
    ],
    'wpn_1157' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05'],
        'qty'       => [45, 35, 27, 18, 5],
        'end'       => 'Available',
    ],
    'wpn_1159' => [
        'materials' => ['material_01', 'material_02', 'material_03', 'material_04', 'material_05', 'material_06'],
        'qty'       => [50, 40, 30, 20, 10, 1],
        'end'       => 'Available',
    ],

    // ── Skill ──
    'skill_2003' => [
        'materials' => ['material_01'],
        'qty'       => [1],
        'end'       => 'Available',
    ],
];
