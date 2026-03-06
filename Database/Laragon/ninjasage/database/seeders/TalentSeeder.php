<?php

namespace Database\Seeders;

use App\Models\GameConfig;
use App\Models\Talent;
use Illuminate\Database\Seeder;

class TalentSeeder extends Seeder
{
    public function run(): void
    {
        $talents = [
            "de"       => ["is_emblem" => false, "price_gold" => 500000,   "price_token" => 0,    "talent_name" => "Dark Eye",             "talent_description" => "Combine eye skill with acupuncture skill to give the user the ability to look through the target's nerves and meridians."],
            "dm"       => ["is_emblem" => false, "price_gold" => 10000000,  "price_token" => 1500, "talent_name" => "Dark Matter",          "talent_description" => ""],
            "dp"       => ["is_emblem" => false, "price_gold" => 500000,   "price_token" => 0,    "talent_name" => "Deadly Performance",   "talent_description" => "Dead Bone performer can summon deceased beings in battles. Advanced performer can manipulate more dead bones at the same time."],
            "lm"       => ["is_emblem" => false, "price_gold" => 10000000,  "price_token" => 1500, "talent_name" => "Light Matter",         "talent_description" => ""],
            "sm"       => ["is_emblem" => true,  "price_gold" => 0,         "price_token" => 5000, "talent_name" => "Soul Marionette",      "talent_description" => "A new body that was created by puppeteer after abandoning their flesh body. Become a full-fledged puppet while still being a puppeteer."],
            "eoc"      => ["is_emblem" => false, "price_gold" => 20000000,  "price_token" => 2500, "talent_name" => "Eye of Creation",      "talent_description" => "The Eye of Creation skills unlock visionary power, enabling users to manifest and reshape intricate realities with unparalleled creativity."],
            "eom"      => ["is_emblem" => true,  "price_gold" => 0,         "price_token" => 400,  "talent_name" => "Eye of Mirror",        "talent_description" => "An ancient eye skill which grants the user strong vision and perception, but it also brings a huge physical burden."],
            "ice"      => ["is_emblem" => false, "price_gold" => 1000000,   "price_token" => 0,    "talent_name" => "Icy Crystal",          "talent_description" => "Combine Wind and Water elements together to form a new element type - Icy Crystal. Use low temperature to attack target or protect yourself."],
            "iron"     => ["is_emblem" => true,  "price_gold" => 0,         "price_token" => 2500, "talent_name" => "Iron Sand",            "talent_description" => "Iron Sand is a powerful skill that manipulates particles to create versatile weapons or defenses. The user controls the iron sand to form barriers and projectiles, making it highly adaptable in combat."],
            "lava"     => ["is_emblem" => false, "price_gold" => 1000000,   "price_token" => 0,    "talent_name" => "Explosive Lava",       "talent_description" => "Explosive Lava is combined by the elements of Fire and Earth. User damage target by ignition and explosion."],
            "wood"     => ["is_emblem" => false, "price_gold" => 1000000,   "price_token" => 0,    "talent_name" => "Enraged Forest",       "talent_description" => "The Enraged Forest is combined by the elements of Water and Earth. User manipulates the growth of tree and uses wood to attack target."],
            "saint"    => ["is_emblem" => false, "price_gold" => 0,         "price_token" => 2500, "talent_name" => "Saint Power",          "talent_description" => "'Saint Power' allows the user to achieve the pinnacle of chakra manipulation by understanding the fundamental essence of life."],
            "sound"    => ["is_emblem" => false, "price_gold" => 1000000,   "price_token" => 0,    "talent_name" => "Demon Sound",          "talent_description" => "The Demon Sound is combined by the elements of Thunder and Wind. Users interfere targets with different kinds of sounds and music."],
            "insect"   => ["is_emblem" => false, "price_gold" => 3000000,   "price_token" => 0,    "talent_name" => "Insect Symbiosis",     "talent_description" => "Years of studying various insects and learning their biology has unraveled the secrets of insect utilization and manipulation."],
            "orochi"   => ["is_emblem" => false, "price_gold" => 0,         "price_token" => 400,  "talent_name" => "Orochi's Rage",        "talent_description" => "Blessed by the great snake Orochi, the user gains protection and immunity to poison and also gains the ability to revive the dead."],
            "shadow"   => ["is_emblem" => true,  "price_gold" => 0,         "price_token" => 400,  "talent_name" => "Hidden Silhouette",    "talent_description" => "Silhouette user manipulates human shadows to restrict and control target. Advanced user can incarnate shadows into physical objects and attack target directly."],
            "crystal"  => ["is_emblem" => false, "price_gold" => 25000000,  "price_token" => 2500, "talent_name" => "Crystal Manifestation","talent_description" => "Crystal Manifestation taps into the latent energies of the environment, channeling them to create intricate and powerful crystalline structures."],
            "eightext" => ["is_emblem" => false, "price_gold" => 0,         "price_token" => 400,  "talent_name" => "Eight Extremities",    "talent_description" => "This talent focuses on the flexibility of 8 body parts and the art of taijutsu. Explosive power can be achieved under extreme mode, but there will be serious side effects."],
            "kot"      => ["is_emblem" => false, "price_gold" => 20000000,  "price_token" => 2500, "talent_name" => "Knowledge of Time",    "talent_description" => "A temporal jutsu that bends time itself. The user converts their own vitality into agility, then channels that agility into devastating bursts of damage.", "skills" => [["talent_skill_id" => "skill_1119"], ["talent_skill_id" => "skill_1120"], ["talent_skill_id" => "skill_1121"], ["talent_skill_id" => "skill_1122"], ["talent_skill_id" => "skill_1123"], ["talent_skill_id" => "skill_1124"]]],
        ];

        foreach ($talents as $id => $data) {
            $fields = [
                'name'         => $data['talent_name'],
                'description'  => $data['talent_description'],
                'price_gold'   => $data['price_gold'],
                'price_tokens' => $data['price_token'],
                'is_emblem'    => $data['is_emblem'],
            ];
            if (isset($data['skills'])) {
                $fields['skills'] = $data['skills'];
            }
            Talent::updateOrCreate(['talent_id' => $id], $fields);
        }

        // Seed all 6 KoT skill levels into public/game_data/talents.json
        $this->seedKotSkillLevels();

        // Register all 6 KoT skills in talent_description GameConfig
        $desc = GameConfig::get('talent_description', []);
        $desc['talent_kot_skill_1'] = ['talent_skill_id' => 'skill_1119', 'talent_skill_name' => 'Knowledge of the Age', 'talent_skill_description' => 'For every X max HP, increase agility by 1.', 'talent_skill_order' => 1];
        $desc['talent_kot_skill_2'] = ['talent_skill_id' => 'skill_1120', 'talent_link_skill_id' => 'skill_1119', 'talent_skill_name' => 'Essence of Time', 'talent_skill_description' => 'For every X Agility points, increase all attack damage by 1%.', 'talent_skill_order' => 2];
        $desc['talent_kot_skill_3'] = ['talent_skill_id' => 'skill_1121', 'talent_link_skill_id' => 'skill_1119', 'talent_skill_name' => 'Garden of Wisdom', 'talent_skill_description' => 'Increase all active buff duration.', 'talent_skill_order' => 3];
        $desc['talent_kot_skill_4'] = ['talent_skill_id' => 'skill_1122', 'talent_link_skill_id' => 'skill_1120', 'talent_skill_name' => 'Future Sentence', 'talent_skill_description' => 'Stores all damage dealt to the enemy. After duration ends, stored damage is applied ignoring damage reduction.', 'talent_skill_order' => 4];
        $desc['talent_kot_skill_5'] = ['talent_skill_id' => 'skill_1123', 'talent_link_skill_id' => 'skill_1121', 'talent_link_skill_id2' => 'skill_1122', 'talent_skill_name' => 'Heaven Judgement', 'talent_skill_description' => 'Inflicts Chaos on all enemies, preventing them from moving.', 'talent_skill_order' => 5];
        $desc['talent_kot_skill_6'] = ['talent_skill_id' => 'skill_1124', 'talent_link_skill_id' => 'skill_1123', 'talent_skill_name' => 'Holy Pillar', 'talent_skill_description' => "Reduces the enemy's CP and inflicts Internal Injury.", 'talent_skill_order' => 6];
        GameConfig::set('talent_description', $desc);
    }

    private function seedKotSkillLevels(): void
    {
        $path = public_path('game_data/talents.json');
        $existing = json_decode(file_get_contents($path), true) ?? [];

        // Remove any stale KoT entries (idempotent re-run)
        $kotBases = ['skill_1119', 'skill_1120', 'skill_1121', 'skill_1122', 'skill_1123', 'skill_1124'];
        $existing = array_values(array_filter($existing, function ($entry) use ($kotBases) {
            return !in_array(explode(':', $entry['id'] ?? '')[0], $kotBases);
        }));

        // skill_1119 — Knowledge of the Age (passive: HP → Agility)
        // StatManager formula: Math.round(base_hp / max_hp_count) * increase_agility
        $skill1119 = [
            1 => [900, 1], 2 => [860, 1], 3 => [820, 1], 4 => [780, 1], 5 => [740, 1],
            6 => [700, 1], 7 => [660, 1], 8 => [620, 1], 9 => [570, 1], 10 => [500, 2],
        ];
        foreach ($skill1119 as $lv => [$hpCount, $agiIncrease]) {
            $existing[] = [
                'id'                       => "skill_1119:{$lv}",
                'talent_skill_name'        => 'Knowledge of the Age',
                'talent_skill_description' => "For every {$hpCount} max HP, increase agility by {$agiIncrease}.",
                'talent_skill_damage'      => 0,
                'talent_skill_cp_cost'     => 0,
                'talent_type'              => 'extreme',
                'max_hp_count'             => $hpCount,
                'increase_agility'         => $agiIncrease,
                'effects'                  => [],
                'skill_cooldown'           => 0,
            ];
        }

        // skill_1120 — Essence of Time (passive: Agility → Damage %)
        // EffectsManager formula: Math.floor(agility / agility_point)
        $skill1120 = [
            1 => 17, 2 => 16, 3 => 15, 4 => 15, 5 => 14,
            6 => 14, 7 => 13, 8 => 13, 9 => 13, 10 => 12,
        ];
        foreach ($skill1120 as $lv => $agiPoint) {
            $existing[] = [
                'id'                       => "skill_1120:{$lv}",
                'talent_skill_name'        => 'Essence of Time',
                'talent_skill_description' => "For every {$agiPoint} Agility points, increase all attack damage by 1%.",
                'talent_skill_damage'      => 0,
                'talent_skill_cp_cost'     => 0,
                'talent_type'              => 'extreme',
                'increase_damage'          => 1,
                'agility_point'            => $agiPoint,
                'effects'                  => [],
                'skill_cooldown'           => 0,
            ];
        }

        // skill_1121 — Garden of Wisdom (active: increase buff duration)
        $skill1121 = [
            1  => [1, 1,    16], 2  => [1, 1.32, 16], 3  => [1, 1.76, 16], 4  => [1, 2.32, 16],
            5  => [1, 3,    16], 6  => [2, 3.8,  16], 7  => [2, 4.72, 16], 8  => [2, 5.76, 16],
            9  => [2, 6.92, 16], 10 => [3, 8.2,  16],
        ];
        foreach ($skill1121 as $lv => [$amount, $cpCost, $cooldown]) {
            $existing[] = [
                'id'                       => "skill_1121:{$lv}",
                'talent_skill_name'        => 'Garden of Wisdom',
                'talent_skill_description' => "Increase all active buff duration by {$amount} turn" . ($amount > 1 ? 's' : '') . ".",
                'talent_skill_damage'      => 0,
                'talent_skill_cp_cost'     => $cpCost,
                'type'                     => 'extreme',
                'skill_target'             => 'Self',
                'effects'                  => [[
                    'reduce_type' => 'MAX', 'amount_cp' => 0, 'amount' => $amount, 'amount_prc' => 0,
                    'amount_protection' => 0, 'calc_type' => 'percent', 'effect' => 'increase_buff_duration',
                    'duration' => 0, 'no_disperse' => false, 'effect_name' => 'Increase Buff Duration',
                    'type' => 'Buff', 'target' => 'self', 'is_debuff' => false, 'amount_hp' => 0,
                ]],
                'skill_cooldown'           => $cooldown,
                'anims'                    => ['hit' => [73]],
                'attack_hit_position'      => 'startpos',
            ];
        }

        // skill_1122 — Future Sentence (active: fast_forward debuff)
        $skill1122 = [
            1  => [1, 1],    2  => [1, 1.7],  3  => [1, 2.6],  4  => [1, 3.7],  5  => [1, 5],
            6  => [2, 6.5],  7  => [3, 8.2],  8  => [2, 10.1], 9  => [2, 12.2], 10 => [3, 14.5],
        ];
        foreach ($skill1122 as $lv => [$duration, $cpCost]) {
            $existing[] = [
                'id'                       => "skill_1122:{$lv}",
                'talent_skill_name'        => 'Future Sentence',
                'talent_skill_description' => "Stores all damage dealt to the enemy for {$duration} turn" . ($duration > 1 ? 's' : '') . ". After the duration ends, the total stored damage is applied as an effect. This damage ignores all damage reduction effects, but can be negated by Debuff Resistance. This effect cannot be purified.",
                'talent_skill_damage'      => 0,
                'talent_skill_cp_cost'     => $cpCost,
                'type'                     => 'extreme',
                'skill_target'             => 'Target',
                'effects'                  => [[
                    'reduce_type' => 'MAX', 'amount_cp' => 0, 'amount' => 0, 'amount_prc' => 0,
                    'amount_protection' => 0, 'calc_type' => 'percent', 'effect' => 'fast_forward',
                    'duration' => $duration, 'no_disperse' => false, 'effect_name' => 'Future Sentence',
                    'type' => 'Debuff', 'target' => 'enemy', 'is_debuff' => false, 'amount_hp' => 0,
                ]],
                'skill_cooldown'           => 16,
                'anims'                    => ['hit' => [142], 'fullscreen' => ['add' => 12, 'remove' => 148]],
                'attack_hit_position'      => 'startpos',
            ];
        }

        // skill_1123 — Heaven Judgement (active: chaos all enemies)
        $skill1123 = [
            1  => [1, 1,    1.34], 2  => [1, 1.26, 1.34], 3  => [1, 1.58, 1.82],
            4  => [1, 1.96, 2.44], 5  => [2, 2.4,  3.2],  6  => [2, 2.9,  4.1],
            7  => [2, 3.46, 5.14], 8  => [3, 4.08, 6.32], 9  => [3, 4.76, 7.64],
            10 => [3, 5.5,  9.1],
        ];
        foreach ($skill1123 as $lv => [$chaosDur, $dmg, $cpCost]) {
            $existing[] = [
                'id'                       => "skill_1123:{$lv}",
                'talent_skill_name'        => 'Heaven Judgement',
                'talent_skill_description' => "Inflicts Chaos on all enemies, preventing them from moving for {$chaosDur} turn" . ($chaosDur > 1 ? 's' : '') . ".",
                'talent_skill_damage'      => $dmg,
                'talent_skill_cp_cost'     => $cpCost,
                'talent_type'              => 'extreme',
                'skill_target'             => 'All',
                'multi_hit'                => true,
                'effects'                  => [[
                    'reduce_type' => 'MAX', 'amount_cp' => 0, 'amount' => 100, 'amount_prc' => 0,
                    'amount_protection' => 0, 'calc_type' => 'percent', 'effect' => 'chaos',
                    'duration' => $chaosDur, 'no_disperse' => false, 'effect_name' => 'Chaos',
                    'multi_hit' => true, 'type' => 'Debuff', 'target' => 'enemy', 'is_debuff' => true, 'amount_hp' => 0,
                ]],
                'skill_cooldown'           => 16,
                'anims'                    => ['hit' => [102], 'fullscreen' => ['add' => 12, 'remove' => 126], 'effects' => [['name' => 'effectMc', 'add' => 30, 'type' => 'target']]],
                'attack_hit_position'      => 'range_3',
            ];
        }

        // skill_1124 — Holy Pillar (active: reduce CP % + internal_injury)
        $skill1124 = [
            1  => [20, 1, 1,    19], 2  => [25, 1, 1.36, 19], 3  => [30, 1, 1.88, 19],
            4  => [34, 1, 2.56, 19], 5  => [38, 2, 3.4,  19], 6  => [42, 2, 4.4,  19],
            7  => [46, 2, 5.56, 19], 8  => [45, 2, 6.88, 19], 9  => [50, 3, 8.36, 19],
            10 => [55, 3, 10,   19],
        ];
        foreach ($skill1124 as $lv => [$cpReduce, $injuryDur, $dmg, $cooldown]) {
            $existing[] = [
                'id'                       => "skill_1124:{$lv}",
                'talent_skill_name'        => 'Holy Pillar',
                'talent_skill_description' => "Reduces the enemy's CP by {$cpReduce}% and inflicts Internal Injury for {$injuryDur} turn" . ($injuryDur > 1 ? 's' : '') . ".",
                'talent_skill_damage'      => $dmg,
                'talent_skill_cp_cost'     => $dmg,
                'type'                     => 'extreme',
                'skill_target'             => 'Target',
                'effects'                  => [
                    [
                        'reduce_type' => 'MAX', 'duration' => 0, 'amount_cp' => 0, 'amount' => $cpReduce,
                        'amount_prc' => 0, 'amount_protection' => 0, 'calc_type' => 'percent',
                        'effect' => 'insta_reduce_max_cp', 'chance' => 100, 'no_disperse' => false,
                        'effect_name' => 'Reduce CP', 'type' => 'Debuff', 'target' => 'enemy', 'is_debuff' => true, 'amount_hp' => 0,
                    ],
                    [
                        'reduce_type' => 'MAX', 'amount_cp' => 0, 'amount' => 0, 'amount_prc' => 0,
                        'type' => 'Debuff', 'target' => 'enemy', 'effect' => 'internal_injury',
                        'duration' => $injuryDur, 'effect_name' => 'Internal Injury', 'calc_type' => 'percent',
                        'is_debuff' => true, 'amount_hp' => 0,
                    ],
                ],
                'skill_cooldown'           => $cooldown,
                'anims'                    => ['hit' => [74], 'fullscreen' => ['add' => 12, 'remove' => 119], 'effects' => [['name' => 'effectMc', 'add' => 18, 'type' => 'target']]],
                'attack_hit_position'      => 'range_3',
            ];
        }

        file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}