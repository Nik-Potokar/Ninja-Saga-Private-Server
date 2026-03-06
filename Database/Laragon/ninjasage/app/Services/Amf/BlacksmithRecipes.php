<?php

/**
 * Blacksmith forge recipes — managed via the admin panel (BlacksmithManager).
 *
 * Format:
 *   'output_item_id' => [
 *       'req_weapon' => 'wpn_XX',          // base weapon required (empty string = none)
 *       'mats'       => ['material_01' => 3, 'material_02' => 1, ...],
 *       'gold'       => 10000,
 *       'tokens'     => 500,
 *   ]
 */
return array (

  // ── Weapon ──
  'wpn_609' => 
  array (
    'req_weapon' => 'wpn_03',
    'mats' => 
    array (
      'material_01' => 1,
    ),
    'gold' => 1000,
    'tokens' => 100,
  ),
  'wpn_119' => 
  array (
    'req_weapon' => 'wpn_24',
    'mats' => 
    array (
      'material_01' => 5,
    ),
    'gold' => 10000,
    'tokens' => 100,
  ),
  'wpn_179' => 
  array (
    'req_weapon' => 'wpn_119',
    'mats' => 
    array (
      'material_01' => 2,
    ),
    'gold' => 10000,
    'tokens' => 400,
  ),
  'wpn_164' => 
  array (
    'req_weapon' => 'wpn_01',
    'mats' => 
    array (
      'material_01' => 3,
    ),
    'gold' => 10000,
    'tokens' => 400,
  ),
  'wpn_293' => 
  array (
    'req_weapon' => 'wpn_23',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 1,
    ),
    'gold' => 11000,
    'tokens' => 500,
  ),
  'wpn_137' => 
  array (
    'req_weapon' => 'wpn_12',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 1,
    ),
    'gold' => 12000,
    'tokens' => 600,
  ),
  'wpn_135' => 
  array (
    'req_weapon' => 'wpn_06',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 1,
    ),
    'gold' => 6500,
    'tokens' => 300,
  ),
  'wpn_294' => 
  array (
    'req_weapon' => 'wpn_36',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 1,
    ),
    'gold' => 13000,
    'tokens' => 500,
  ),
  'wpn_295' => 
  array (
    'req_weapon' => 'wpn_50',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 2,
    ),
    'gold' => 15000,
    'tokens' => 500,
  ),
  'wpn_131' => 
  array (
    'req_weapon' => 'wpn_111',
    'mats' => 
    array (
      'material_01' => 2,
      'material_02' => 1,
    ),
    'gold' => 7500,
    'tokens' => 100,
  ),
  'wpn_125' => 
  array (
    'req_weapon' => 'wpn_39',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
    ),
    'gold' => 15000,
    'tokens' => 500,
  ),
  'wpn_181' => 
  array (
    'req_weapon' => 'wpn_125',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 1,
    ),
    'gold' => 15000,
    'tokens' => 200,
  ),
  'wpn_296' => 
  array (
    'req_weapon' => 'wpn_31',
    'mats' => 
    array (
      'material_01' => 7,
      'material_02' => 2,
    ),
    'gold' => 17000,
    'tokens' => 600,
  ),
  'wpn_140' => 
  array (
    'req_weapon' => 'wpn_16',
    'mats' => 
    array (
      'material_01' => 6,
      'material_02' => 3,
    ),
    'gold' => 18000,
    'tokens' => 600,
  ),
  'wpn_297' => 
  array (
    'req_weapon' => 'wpn_47',
    'mats' => 
    array (
      'material_01' => 8,
      'material_02' => 3,
    ),
    'gold' => 19000,
    'tokens' => 600,
  ),
  'wpn_303' => 
  array (
    'req_weapon' => 'wpn_301',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 1,
    ),
    'gold' => 2000,
    'tokens' => 400,
  ),
  'wpn_136' => 
  array (
    'req_weapon' => 'wpn_131',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 1,
      'material_03' => 1,
    ),
    'gold' => 10000,
    'tokens' => 300,
  ),
  'wpn_139' => 
  array (
    'req_weapon' => 'wpn_37',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 2,
      'material_03' => 1,
    ),
    'gold' => 10000,
    'tokens' => 300,
  ),
  'wpn_304' => 
  array (
    'req_weapon' => 'wpn_301',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 1,
    ),
    'gold' => 2000,
    'tokens' => 400,
  ),
  'wpn_305' => 
  array (
    'req_weapon' => 'wpn_301',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 1,
    ),
    'gold' => 2000,
    'tokens' => 400,
  ),
  'wpn_138' => 
  array (
    'req_weapon' => 'wpn_07',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 2,
      'material_03' => 1,
    ),
    'gold' => 10000,
    'tokens' => 300,
  ),
  'wpn_141' => 
  array (
    'req_weapon' => 'wpn_21',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
    ),
    'gold' => 20000,
    'tokens' => 300,
  ),
  'wpn_143' => 
  array (
    'req_weapon' => 'wpn_20',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
    ),
    'gold' => 20000,
    'tokens' => 300,
  ),
  'wpn_126' => 
  array (
    'req_weapon' => 'wpn_42',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 1,
    ),
    'gold' => 21000,
    'tokens' => 800,
  ),
  'wpn_134' => 
  array (
    'req_weapon' => 'wpn_05',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 4,
      'material_03' => 1,
    ),
    'gold' => 11000,
    'tokens' => 400,
  ),
  'wpn_298' => 
  array (
    'req_weapon' => 'wpn_44',
    'mats' => 
    array (
      'material_01' => 6,
      'material_02' => 3,
      'material_03' => 2,
    ),
    'gold' => 23000,
    'tokens' => 800,
  ),
  'wpn_142' => 
  array (
    'req_weapon' => 'wpn_25',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 2,
      'material_03' => 2,
    ),
    'gold' => 25000,
    'tokens' => 300,
  ),
  'wpn_127' => 
  array (
    'req_weapon' => 'wpn_40',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 2,
    ),
    'gold' => 25000,
    'tokens' => 800,
  ),
  'wpn_144' => 
  array (
    'req_weapon' => 'wpn_85',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
    ),
    'gold' => 13500,
    'tokens' => 600,
  ),
  'wpn_611' => 
  array (
    'req_weapon' => 'wpn_610',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 3,
      'material_03' => 2,
      'material_04' => 1,
    ),
    'gold' => 15000,
    'tokens' => 400,
  ),
  'wpn_307' => 
  array (
    'req_weapon' => 'wpn_302',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 3,
      'material_04' => 1,
    ),
    'gold' => 3000,
    'tokens' => 600,
  ),
  'wpn_145' => 
  array (
    'req_weapon' => 'wpn_49',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 3,
      'material_03' => 2,
      'material_04' => 1,
    ),
    'gold' => 15000,
    'tokens' => 300,
  ),
  'wpn_306' => 
  array (
    'req_weapon' => 'wpn_302',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 3,
      'material_04' => 1,
    ),
    'gold' => 3000,
    'tokens' => 600,
  ),
  'wpn_299' => 
  array (
    'req_weapon' => 'wpn_136',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 3,
      'material_03' => 3,
      'material_04' => 1,
    ),
    'gold' => 30000,
    'tokens' => 800,
  ),
  'wpn_146' => 
  array (
    'req_weapon' => 'wpn_45',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 3,
      'material_03' => 2,
      'material_04' => 1,
    ),
    'gold' => 15000,
    'tokens' => 400,
  ),
  'wpn_128' => 
  array (
    'req_weapon' => 'wpn_56',
    'mats' => 
    array (
      'material_01' => 8,
      'material_02' => 5,
      'material_03' => 3,
      'material_04' => 1,
    ),
    'gold' => 30000,
    'tokens' => 800,
  ),
  'wpn_308' => 
  array (
    'req_weapon' => 'wpn_302',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 5,
      'material_03' => 3,
      'material_04' => 1,
    ),
    'gold' => 3000,
    'tokens' => 600,
  ),
  'wpn_291' => 
  array (
    'req_weapon' => 'wpn_59',
    'mats' => 
    array (
      'material_01' => 8,
      'material_02' => 5,
      'material_03' => 3,
      'material_04' => 2,
    ),
    'gold' => 33000,
    'tokens' => 800,
  ),
  'wpn_130' => 
  array (
    'req_weapon' => 'wpn_79',
    'mats' => 
    array (
      'material_01' => 10,
      'material_02' => 6,
      'material_03' => 4,
      'material_04' => 3,
    ),
    'gold' => 34000,
    'tokens' => 800,
  ),
  'wpn_148' => 
  array (
    'req_weapon' => 'wpn_86',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 2,
      'material_03' => 2,
      'material_04' => 1,
    ),
    'gold' => 35000,
    'tokens' => 800,
  ),
  'wpn_129' => 
  array (
    'req_weapon' => 'wpn_54',
    'mats' => 
    array (
      'material_01' => 10,
      'material_02' => 8,
      'material_03' => 6,
      'material_04' => 3,
      'material_05' => 1,
    ),
    'gold' => 35000,
    'tokens' => 800,
  ),
  'wpn_292' => 
  array (
    'req_weapon' => 'wpn_88',
    'mats' => 
    array (
      'material_01' => 9,
      'material_02' => 6,
      'material_03' => 4,
      'material_04' => 5,
      'material_05' => 1,
    ),
    'gold' => 42000,
    'tokens' => 800,
  ),
  'wpn_150' => 
  array (
    'req_weapon' => 'wpn_46',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 3,
      'material_03' => 1,
      'material_04' => 1,
      'material_05' => 1,
    ),
    'gold' => 25000,
    'tokens' => 300,
  ),
  'wpn_612' => 
  array (
    'req_weapon' => 'wpn_611',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 3,
      'material_03' => 1,
      'material_04' => 1,
      'material_05' => 1,
    ),
    'gold' => 25000,
    'tokens' => 300,
  ),
  'wpn_151' => 
  array (
    'req_weapon' => 'wpn_60',
    'mats' => 
    array (
      'material_01' => 4,
      'material_02' => 3,
      'material_03' => 1,
      'material_04' => 1,
      'material_05' => 1,
    ),
    'gold' => 25000,
    'tokens' => 300,
  ),
  'wpn_336' => 
  array (
    'req_weapon' => 'wpn_70',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_339' => 
  array (
    'req_weapon' => 'wpn_107',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_333' => 
  array (
    'req_weapon' => 'wpn_62',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_332' => 
  array (
    'req_weapon' => 'wpn_10',
    'mats' => 
    array (
      'material_01' => 10,
      'material_02' => 7,
      'material_03' => 5,
      'material_04' => 4,
      'material_05' => 3,
    ),
    'gold' => 120000,
    'tokens' => 800,
  ),
  'wpn_335' => 
  array (
    'req_weapon' => 'wpn_69',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_338' => 
  array (
    'req_weapon' => 'wpn_76',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_337' => 
  array (
    'req_weapon' => 'wpn_71',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_334' => 
  array (
    'req_weapon' => 'wpn_63',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
      'material_03' => 2,
      'material_04' => 1,
      'material_05' => 2,
    ),
    'gold' => 30000,
    'tokens' => 400,
  ),
  'wpn_1139' => 
  array (
    'req_weapon' => 'wpn_1138',
    'mats' => 
    array (
      'material_01' => 3,
      'material_02' => 2,
    ),
    'gold' => 1000000,
    'tokens' => 1000,
  ),
  'wpn_1141' => 
  array (
    'req_weapon' => 'wpn_1140',
    'mats' => 
    array (
      'material_01' => 5,
      'material_02' => 4,
    ),
    'gold' => 2000000,
    'tokens' => 2000,
  ),
  'wpn_1143' => 
  array (
    'req_weapon' => 'wpn_1142',
    'mats' => 
    array (
      'material_01' => 7,
      'material_02' => 5,
      'material_03' => 3,
    ),
    'gold' => 3000000,
    'tokens' => 3000,
  ),
  'wpn_1145' => 
  array (
    'req_weapon' => 'wpn_1144',
    'mats' => 
    array (
      'material_01' => 15,
      'material_02' => 13,
      'material_03' => 7,
    ),
    'gold' => 4000000,
    'tokens' => 4000,
  ),
  'wpn_1147' => 
  array (
    'req_weapon' => 'wpn_1146',
    'mats' => 
    array (
      'material_01' => 15,
      'material_02' => 13,
      'material_03' => 7,
    ),
    'gold' => 4000000,
    'tokens' => 4000,
  ),
  'wpn_1149' => 
  array (
    'req_weapon' => 'wpn_1148',
    'mats' => 
    array (
      'material_01' => 24,
      'material_02' => 17,
      'material_03' => 12,
    ),
    'gold' => 5000000,
    'tokens' => 5000,
  ),
  'wpn_1151' => 
  array (
    'req_weapon' => 'wpn_1150',
    'mats' => 
    array (
      'material_01' => 30,
      'material_02' => 20,
      'material_03' => 15,
      'material_04' => 6,
    ),
    'gold' => 6000000,
    'tokens' => 6000,
  ),
  'wpn_1153' => 
  array (
    'req_weapon' => 'wpn_1152',
    'mats' => 
    array (
      'material_01' => 35,
      'material_02' => 27,
      'material_03' => 23,
      'material_04' => 15,
    ),
    'gold' => 7000000,
    'tokens' => 7000,
  ),
  'wpn_1155' => 
  array (
    'req_weapon' => 'wpn_1154',
    'mats' => 
    array (
      'material_01' => 40,
      'material_02' => 33,
      'material_03' => 25,
      'material_04' => 15,
      'material_05' => 3,
    ),
    'gold' => 8000000,
    'tokens' => 8000,
  ),
  'wpn_1157' => 
  array (
    'req_weapon' => 'wpn_1156',
    'mats' => 
    array (
      'material_01' => 45,
      'material_02' => 35,
      'material_03' => 27,
      'material_04' => 18,
      'material_05' => 5,
    ),
    'gold' => 9000000,
    'tokens' => 9000,
  ),
  'wpn_1159' => 
  array (
    'req_weapon' => 'wpn_1158',
    'mats' => 
    array (
      'material_01' => 50,
      'material_02' => 40,
      'material_03' => 30,
      'material_04' => 20,
      'material_05' => 10,
      'material_06' => 1,
    ),
    'gold' => 10000000,
    'tokens' => 9999,
  ),
  'wpn_165' => 
  array (
    'req_weapon' => 'wpn_01',
    'mats' => 
    array (
      'material_01' => 1,
    ),
    'gold' => 123,
    'tokens' => 1000,
  ),
);
