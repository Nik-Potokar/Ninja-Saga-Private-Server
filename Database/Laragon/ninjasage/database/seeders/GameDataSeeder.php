<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Skill;
use App\Models\Pet;
use App\Models\Mission;
use App\Models\Npc;
use App\Models\Enemy;
use App\Models\Talent;
use App\Models\Senjutsu;
use App\Models\GameConfig;
use App\Models\XP;
use Illuminate\Support\Facades\Storage;

class GameDataSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Truncate tables to ensure a clean slate
        Item::query()->delete();
        Skill::query()->delete();
        Pet::query()->delete();
        Mission::query()->delete();
        Npc::query()->delete();
        Enemy::query()->delete();
        Talent::query()->delete();
        Senjutsu::query()->delete();
        GameConfig::query()->delete();

        // 1. Items (from library.json)
        $items = $this->loadJson('library');
        foreach ($items as $data) {
            $category = $data['category'] ?? 'item';
            
            // Auto-detect category from ID if default
            if ($category === 'item') {
                if (str_starts_with($data['id'], 'wpn_')) $category = 'weapon';
                elseif (str_starts_with($data['id'], 'back_')) $category = 'back';
                elseif (str_starts_with($data['id'], 'set_')) $category = 'set';
                elseif (str_starts_with($data['id'], 'hair_')) $category = 'hair';
                elseif (str_starts_with($data['id'], 'accessory_')) $category = 'accessory';
                elseif (str_starts_with($data['id'], 'material_')) $category = 'material';
                elseif (str_starts_with($data['id'], 'essential_')) $category = 'essential';
            }

            Item::create([
                'item_id' => $data['id'],
                'name' => $data['name'] ?? 'Unknown',
                'level' => $data['level'] ?? 0,
                'price_gold' => $data['price_gold'] ?? 0,
                'price_tokens' => $data['price_tokens'] ?? 0,
                'category' => $category,
                'premium' => $data['premium'] ?? false,
            ]);
        }

        // 2. Skills (from skills.json)
        $skills = $this->loadJson('skills');
        foreach ($skills as $data) {
            Skill::create([
                'skill_id' => $data['id'],
                'name' => $data['name'] ?? 'Unknown',
                'level' => $data['level'] ?? 1,
                'element' => intval($data['type'] ?? 0),
                'price_gold' => $data['price_gold'] ?? 0,
                'price_tokens' => $data['price_tokens'] ?? 0,
                'premium' => $data['premium'] ?? false,
            ]);
        }

        // 3. Pets (from pet.json)
        $pets = $this->loadJson('pet');
        $petIdMap = [
            'pet_01' => '1',
            'pet_02' => '2',
            'pet_easa' => '3',
            'pet_keiko' => '4',
            'pet_26941' => '5',
        ];

        foreach ($pets as $data) {
            $originalId = $data['id'];
            $swfName = $data['swf'] ?? $originalId;
            
            // Fix swf name if it doesn't start with pet_
            if (!str_starts_with($swfName, 'pet_')) {
                $swfName = 'pet_' . $swfName;
            }
            // Prevent double prefix if somehow it ends up as pet_pet_
            if (str_starts_with($swfName, 'pet_pet_')) {
                $swfName = str_replace('pet_pet_', 'pet_', $swfName);
            }

            // Map ID if needed
            $finalId = $petIdMap[$originalId] ?? $originalId;

            Pet::create([
                'pet_id' => $finalId,
                'name' => $data['name'] ?? 'Unknown',
                'swf' => $swfName,
                'price_gold' => $data['price_gold'] ?? 0,
                'price_tokens' => $data['price_tokens'] ?? 0,
                'premium' => $data['premium'] ?? false,
                'skills' => $data['attacks'] ?? [],
            ]);
        }

        // 4. Missions (from mission.json)
        $missions = $this->loadJson('mission');
        foreach ($missions as $data) {
            Mission::create([
                'mission_id' => $data['id'],
                'req_lvl' => $data['level'] ?? 1,
                'xp' => $data['rewards']['xp'] ?? 0,
                'gold' => $data['rewards']['gold'] ?? 0,
            ]);
        }

        // 5. NPCs (from npc.json)
        $npcs = $this->loadJson('npc');
        $premiumNpcList = [
            'npc_102' => ['tokens' => 3], // Kojima
            'npc_101' => ['tokens' => 2], // Pak Ustadz
        ];
        $importedNpcIds = [];
        foreach ($npcs as $data) {
            if (in_array($data['id'], $importedNpcIds)) continue;
            
            Npc::create([
                'npc_id' => $data['id'],
                'name' => $data['name'],
                'level' => $data['level'],
                'rank' => $data['rank'],
                'hp' => $data['hp'],
                'cp' => $data['cp'],
                'agility' => $data['agility'],
                'dodge' => $data['dodge'],
                'critical' => $data['critical'],
                'accuracy' => $data['accuracy'],
                'purify' => $data['purify'],
                'description' => $data['description'] ?? '',
                'attacks' => $data['attacks'],
                'price_gold' => $data['price_gold'] ?? 0,
                'price_tokens' => $premiumNpcList[$data['id']]['tokens'] ?? 0,
                'premium' => $data['premium'] ?? (isset($premiumNpcList[$data['id']]) ? true : false),
            ]);
            $importedNpcIds[] = $data['id'];
        }

        // 6. Enemies (from enemy.json)
        $enemies = $this->loadJson('enemy');
        foreach ($enemies as $data) {
            Enemy::create([
                'enemy_id' => $data['id'],
                'name' => $data['name'],
                'level' => $data['level'],
                'hp' => $data['hp'],
                'cp' => $data['cp'],
                'agility' => $data['agility'],
                'attacks' => $data['attacks'] ?? [],
            ]);
        }

        // 7. Talents (from talents.json + Hardcoded backup)
        $jsonTalents = $this->loadJson('talents');
        $mergedTalents = [];
        foreach ($jsonTalents as $t) {
            $mergedTalents[$t['id']] = $t;
        }

        $hardcodedTalents = [
            "de" => ["is_emblem" => false, "price_gold" => 500000, "price_token" => 0, "talent_name" => "Dark Eye", "talent_description" => "Combine eye skill with acupuncture skill to give the user the ability to look through the target's nerves and meridians."],
            "dm" => ["is_emblem" => false, "price_gold" => 10000000, "price_token" => 1500, "talent_name" => "Dark Matter", "talent_description" => ""],
            "dp" => ["is_emblem" => false, "price_gold" => 500000, "price_token" => 0, "talent_name" => "Deadly Performance", "talent_description" => "Dead Bone performer can summon deceased beings in battles. Advanced performer can manipulate more dead bones at the same time."],
            "lm" => ["is_emblem" => false, "price_gold" => 10000000, "price_token" => 1500, "talent_name" => "Light Matter", "talent_description" => ""],
            "sm" => ["is_emblem" => true, "price_gold" => 0, "price_token" => 5000, "talent_name" => "Soul Marionette", "talent_description" => "A new body that was created by puppeteer after abandoning their flesh body. Become a full-fledged puppet while still being a puppeteer."],
            "eoc" => ["is_emblem" => false, "price_gold" => 20000000, "price_token" => 2500, "talent_name" => "Eye of Creation", "talent_description" => "The Eye of Creation skills unlock visionary power, enabling users to manifest and reshape intricate realities with unparalleled creativity."],
            "eom" => ["is_emblem" => true, "price_gold" => 0, "price_token" => 400, "talent_name" => "Eye of Mirror", "talent_description" => "An ancient eye skill which grants the user strong vision and perception, but it also brings a huge physical burden."],
            "ice" => ["is_emblem" => false, "price_gold" => 1000000, "price_token" => 0, "talent_name" => "Icy Crystal", "talent_description" => "Combine Wind and Water elements together to form a new element type - Icy Crystal. Use low temperature to attack target or protect yourself."],
            "iron" => ["is_emblem" => true, "price_gold" => 0, "price_token" => 2500, "talent_name" => "Iron Sand", "talent_description" => "Iron Sand is a powerful skill that manipulates particles to create versatile weapons or defenses. The user controls the iron sand to form barriers and projectiles, making it highly adaptable in combat."],
            "lava" => ["is_emblem" => false, "price_gold" => 1000000, "price_token" => 0, "talent_name" => "Explosive Lava", "talent_description" => "Explosive Lava is combined by the elements of Fire and Earth. User damage target by ignition and explosion."],
            "wood" => ["is_emblem" => false, "price_gold" => 1000000, "price_token" => 0, "talent_name" => "Enraged Forest", "talent_description" => "The Enraged Forest is combined by the elements of Water and Earth. User manipulates the growth of tree and uses wood to attack target."],
            "saint" => ["is_emblem" => false, "price_gold" => 0, "price_token" => 2500, "talent_name" => "Saint Power", "talent_description" => "'Saint Power' allows the user to achieve the pinnacle of chakra manipulation by understanding the fundamental essence of life."],
            "sound" => ["is_emblem" => false, "price_gold" => 1000000, "price_token" => 0, "talent_name" => "Demon Sound", "talent_description" => "The Demon Sound is combined by the elements of Thunder and Wind. Users interfere targets with different kinds of sounds and music."],
            "insect" => ["is_emblem" => false, "price_gold" => 3000000, "price_token" => 0, "talent_name" => "Insect Symbiosis", "talent_description" => "Years of studying various insects and learning their biology has unraveled the secrets of insect utilization and manipulation."],
            "orochi" => ["is_emblem" => false, "price_gold" => 0, "price_token" => 400, "talent_name" => "Orochi's Rage", "talent_description" => "Blessed by the great snake Orochi, the user gains protection and immunity to poison and also gains the ability to revive the dead."],
            "shadow" => ["is_emblem" => true, "price_gold" => 0, "price_token" => 400, "talent_name" => "Hidden Silhouette", "talent_description" => "Silhouette user manipulates human shadows to restrict and control target. Advanced user can incarnate shadows into physical objects and attack target directly."],
            "crystal" => ["is_emblem" => false, "price_gold" => 25000000, "price_token" => 2500, "talent_name" => "Crystal Manifestation", "talent_description" => "Crystal Manifestation taps into the latent energies of the environment, channeling them to create intricate and powerful crystalline structures."],
            "eightext" => ["is_emblem" => false, "price_gold" => 0, "price_token" => 400, "talent_name" => "Eight Extremities", "talent_description" => "This talent focuses on the flexibility of 8 body parts and the art of taijutsu. Explosive power can be achieved under extreme mode, but there will be serious side effects."],
            "kot" => ["is_emblem" => false, "price_gold" => 20000000, "price_token" => 2500, "talent_name" => "Knowledge of Time", "talent_description" => "A temporal jutsu that bends time itself. The user converts their own vitality into agility, then channels that agility into devastating bursts of damage."]
        ];

        foreach ($hardcodedTalents as $id => $data) {
            $existing = $mergedTalents[$id] ?? [];
            $mergedTalents[$id] = array_merge($existing, [
                'id' => $id,
                'talent_skill_name' => $data['talent_name'],
                'talent_skill_description' => $data['talent_description'],
                'price_gold' => $data['price_gold'],
                'price_token' => $data['price_token'],
                'is_emblem' => $data['is_emblem'],
                'skills' => $existing['skills'] ?? [] // Preserve skills if present in JSON
            ]);
        }

        foreach ($mergedTalents as $data) {
            Talent::create([
                'talent_id' => $data['id'],
                'name' => $data['talent_skill_name'] ?? ('Talent ' . $data['id']),
                'description' => $data['talent_skill_description'] ?? '',
                'skills' => $data['skills'] ?? [],
                'price_gold' => $data['price_gold'] ?? 0,
                'price_tokens' => $data['price_token'] ?? 0,
                'is_emblem' => $data['is_emblem'] ?? false,
            ]);
        }

        // 8. Senjutsu (from senjutsu.json)
        $senjutsus = $this->loadJson('senjutsu');
        foreach ($senjutsus as $data) {
            Senjutsu::create([
                'senjutsu_id' => $data['id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'effects' => $data,
            ]);
        }

        // 9. GameConfigs (from gamedata.json)
        $gameData = $this->loadJson('gamedata');
        foreach ($gameData as $node) {
            GameConfig::set($node['id'], $node['data']);
        }

        // 9b. Daily Rewards Config
        GameConfig::set('daily_rewards', [
            'token_amount' => 25,
            'double_xp_chance' => 20,
            'xp_min_multiplier' => 50,
            'xp_max_multiplier' => 100,
        ]);

        // 10. Manual Configs
        GameConfig::set('attendance_rewards', [
            ['id' => 'att_1', 'price' => 1, 'item' => 'gold_5000'],
            ['id' => 'att_2', 'price' => 5, 'item' => 'xp_1000'],
            ['id' => 'att_3', 'price' => 10, 'item' => 'essential_01:5'],
            ['id' => 'att_4', 'price' => 15, 'item' => 'tokens_50'],
            ['id' => 'att_5', 'price' => 20, 'item' => 'item_45:10'],
            ['id' => 'att_6', 'price' => 25, 'item' => 'wpn_01'],
        ]);

        GameConfig::set('roulette_rewards', [
            1 => 'tokens_1',
            2 => 'gold_500',
            3 => 'xp_100',
            4 => 'tokens_3',
            5 => 'gold_10000',
            6 => 'xp_200',
            7 => 'tokens_5',
            8 => 'gold_2000',
            9 => 'xp_500',
            10 => 'tokens_10'
        ]);

        GameConfig::set('scratch_rewards', [
            'tokens_15',
            'gold_50000',
            'xp_percent_2',
            'tp_15',
            'item_mini_talent_pill',
            'item_small_talent_pill',
            'item_moyai_coin',
            'item_ninja_seal_gan',
            'item_ninja_blood_gan',
            'item_rename_badge',
            'wpn_shinobu',
            'wpn_wicked',
            'wpn_yakumo',
            'back_musket',
            'back_shadow_twin',
            'accessory_silver_wolf',
            'pet_rakura',
            'skill_fan_god'
        ]);

        GameConfig::set('chunin_package', [
            'cost' => 2000,
            'rewards' => [
                ['type' => 'skill', 'id' => 'skill_399'],
                ['type' => 'item', 'id' => 'wpn_864'],
                ['type' => 'item', 'id' => 'back_536'],
            ]
        ]);

        // 12. Advanced Academy Skill Chains
        GameConfig::set('academy_chains', [
            'wind' => [
                'evasion' => ["skill_39","skill_271","skill_272","skill_273","skill_274","skill_275"],
                'blade_of_wind' => ["skill_85","skill_276","skill_277","skill_278","skill_279"],
                'wind_peace' => ["skill_161","skill_280","skill_281","skill_282"],
                'dance_of_fujin' => ["skill_151","skill_283","skill_284"],
                'breakthrough' => ["skill_285","skill_286"],
            ],
            'fire' => [
                'fire_power' => ["skill_36","skill_220","skill_221","skill_222","skill_223","skill_224"],
                'hell_fire' => ["skill_86","skill_225","skill_226","skill_227","skill_228"],
                'fire_energy' => ["skill_162","skill_229","skill_230","skill_231"],
                'rage' => ["skill_152","skill_232","skill_233"],
                'phoenix' => ["skill_234","skill_235"],
            ],
            'thunder' => [
                'charge' => ["skill_35","skill_288","skill_289","skill_290","skill_291","skill_292"],
                'flash' => ["skill_87","skill_293","skill_294","skill_295","skill_296"],
                'bundle' => ["skill_163","skill_297","skill_298","skill_299"],
                'armor' => ["skill_153","skill_300","skill_301"],
                'boost' => ["skill_302","skill_303"],
            ],
            'earth' => [
                'golem' => ["skill_59","skill_237","skill_238","skill_239","skill_240","skill_241"],
                'absorb' => ["skill_88","skill_242","skill_243","skill_244","skill_245"],
                'rocks' => ["skill_164","skill_246","skill_247","skill_248"],
                'embrace' => ["skill_154","skill_249","skill_250"],
                'gaunt' => ["skill_251","skill_252"],
            ],
            'water' => [
                'renewal' => ["skill_60","skill_254","skill_255","skill_256","skill_257","skill_258"],
                'bundle' => ["skill_89","skill_259","skill_260","skill_261","skill_262"],
                'prison' => ["skill_165","skill_264","skill_263","skill_265"],
                'shield' => ["skill_155","skill_266","skill_267"],
                'shark' => ["skill_268","skill_269"],
            ],
            'genjutsu' => [
                'sealing' => ["skill_706","skill_726"]
            ]
        ]);

        // 11. XP (from exp node in gamedata)
        $expTable = GameConfig::get('exp');
        if ($expTable) {
            foreach ($expTable as $level => $xp) {
                XP::updateOrCreate(['level' => $level], [
                    'character_xp' => $xp,
                    'pet_xp' => 999999999,
                ]);
            }
        }
    }

    private function loadJson($file)
    {
        $path = "game_data/{$file}.json";
        if (!Storage::disk('local')->exists($path)) {
            echo "File not found: storage/app/private/{$path}\n";
            return [];
        }
        return json_decode(Storage::disk('local')->get($path), true);
    }
}