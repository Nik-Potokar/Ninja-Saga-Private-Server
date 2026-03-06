<?php

/**
 * Hunting Market forge recipes — managed via the admin panel (HuntingMarketManager).
 *
 * Format:
 *   'output_item_id' => [
 *       'materials' => ['material_id_1', 'material_id_2', ...],
 *       'qty'       => [5, 3, ...],
 *   ]
 *
 * Item-ID prefix determines the client tab:
 *   wpn_ → Weapon   set_ → Set   back_ → Back   accessory_ → Accessory
 *   hair_ → Hair     skill_ → Skill   pet_ → Pet   material_ → Material
 */
return [

    // ── Weapon ──
    'wpn_81' => [
        'materials' => ['material_509'],
        'qty'       => [10],
    ],
    'wpn_82' => [
        'materials' => ['material_509'],
        'qty'       => [15],
    ],
    'wpn_83' => [
        'materials' => ['material_509'],
        'qty'       => [20],
    ],
    'wpn_84' => [
        'materials' => ['material_509'],
        'qty'       => [25],
    ],
    'wpn_85' => [
        'materials' => ['material_509'],
        'qty'       => [30],
    ],
    'wpn_86' => [
        'materials' => ['material_509'],
        'qty'       => [35],
    ],

    // ── Pet ──
    'pet_divinewolf' => [
        'materials' => ['material_509'],
        'qty'       => [100],
    ],
];
