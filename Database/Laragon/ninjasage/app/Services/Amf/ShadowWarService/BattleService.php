<?php

namespace App\Services\Amf\ShadowWarService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Models\Pet;
use App\Models\ShadowWarBattle;
use App\Models\ShadowWarSeason;
use App\Models\ShadowWarSquad;
use App\Models\User;
use App\Services\Amf\CharacterService\InfoService;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BattleService
{
    use ValidatesSession;

    /**
     * getEnemies
     * Params: [charId, sessionKey]
     */
    public function getEnemies($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.getEnemies: Char $charId");

        $enemies = $this->buildEnemies($charId);

        return [
            'status' => 1,
            'error' => 0,
            'enemies' => $enemies,
        ];
    }

    /**
     * refreshEnemies
     * Params: [charId, sessionKey]
     */
    public function refreshEnemies($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.refreshEnemies: Char $charId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        if ($user->tokens < 40) {
            return ['status' => 2, 'result' => 'Not enough tokens'];
        }

        $user->tokens -= 40;
        $user->save();

        return [
            'status' => 1,
            'error' => 0,
            'result' => 'Enemies refreshed',
            'enemies' => $this->buildEnemies($charId),
        ];
    }

    /**
     * refillEnergy
     * Params: [charId, sessionKey]
     */
    public function refillEnergy($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.refillEnergy: Char $charId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $activeSeason = ShadowWarSeason::where('active', true)->orderByDesc('num')->first();
        if (!$activeSeason) {
            return ['status' => 2, 'result' => 'No active season'];
        }

        $assignment = ShadowWarSquad::where('character_id', $char->id)
            ->where('season_id', $activeSeason->id)
            ->first();
        if (!$assignment) {
            return ['status' => 2, 'result' => 'No squad assignment'];
        }

        $today = now()->toDateString();
        if ($assignment->energy_last_reset !== $today) {
            $assignment->energy = 100;
            $assignment->energy_last_reset = $today;
            $assignment->energy_refills_today = 0;
        }

        if ($assignment->energy_refills_today >= 10) {
            return ['status' => 2, 'result' => 'Daily refill limit reached'];
        }

        if ($user->tokens < 50) {
            return ['status' => 2, 'result' => 'Not enough tokens'];
        }

        $user->tokens -= 50;
        $user->save();

        $assignment->energy = 100;
        $assignment->energy_refills_today += 1;
        $assignment->save();

        return [
            'status' => 1,
            'error' => 0,
            'energy' => (int)$assignment->energy,
        ];
    }

    /**
     * startBattle
     * Params: [charId, sessionKey, enemyId]
     */
    public function startBattle($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        $enemyId = $params[2] ?? null;
        Log::info("AMF ShadowWar.startBattle: Char $charId Enemy $enemyId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $activeSeason = ShadowWarSeason::where('active', true)->orderByDesc('num')->first();
        if (!$activeSeason) {
            return ['status' => 2, 'result' => 'No active season'];
        }

        $assignment = ShadowWarSquad::where('character_id', $char->id)
            ->where('season_id', $activeSeason->id)
            ->first();
        if (!$assignment) {
            return ['status' => 2, 'result' => 'No squad assignment'];
        }

        $today = now()->toDateString();
        if ($assignment->energy_last_reset !== $today) {
            $assignment->energy = 100;
            $assignment->energy_last_reset = $today;
            $assignment->energy_refills_today = 0;
        }

        if ($assignment->energy < 10) {
            $assignment->save();
            return ['status' => 2, 'result' => 'Not enough energy'];
        }

        $energyBefore = (int)$assignment->energy;
        $assignment->energy -= 10;
        $assignment->save();

        $battleCode = Str::random(10);
        Cache::put("shadowwar_battle_$charId", [
            'id' => $battleCode,
            'enemy_id' => $enemyId,
            'energy_before' => $energyBefore,
            'energy_after' => (int)$assignment->energy,
            'started_at' => now(),
        ], 1800);

        return [
            'status' => 1,
            'error' => 0,
            'id' => $battleCode,
            'energy' => (int)$assignment->energy,
        ];
    }

    /**
     * finishBattle
     * Params: [charId, sessionKey, battleCode, totalDamage, battleData, hash]
     */
    public function finishBattle($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        $battleCode = $params[2] ?? null;
        $totalDamage = (int)($params[3] ?? 0);
        Log::info("AMF ShadowWar.finishBattle: Char $charId Battle $battleCode");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $activeSeason = ShadowWarSeason::where('active', true)->orderByDesc('num')->first();
        if (!$activeSeason) {
            return ['status' => 2, 'result' => 'No active season'];
        }

        $assignment = ShadowWarSquad::where('character_id', $char->id)
            ->where('season_id', $activeSeason->id)
            ->first();
        if (!$assignment) {
            return ['status' => 2, 'result' => 'No squad assignment'];
        }

        $battleData = $params[4] ?? '';
        $won = $totalDamage > 0 && is_string($battleData) && $battleData !== '';

        $cached = Cache::get("shadowwar_battle_$charId");
        $enemyId = $cached['enemy_id'] ?? null;
        $enemyChar = $enemyId ? Character::find($enemyId) : null;
        $enemyAssignment = null;
        if ($enemyChar) {
            $enemyAssignment = ShadowWarSquad::where('character_id', $enemyChar->id)
                ->where('season_id', $activeSeason->id)
                ->first();
        }

        $playerStrength = $this->calculateStrength($assignment->trophy, $char->level, $assignment->rank);
        $enemyStrength = $enemyChar
            ? $this->calculateStrength(
                $enemyAssignment?->trophy ?? 0,
                $enemyChar->level,
                $enemyAssignment?->rank ?? 0
            )
            : $playerStrength;

        $expected = 1 / (1 + pow(10, ($enemyStrength - $playerStrength) / 400));
        $kFactor = 32;
        $delta = (int)round($kFactor * (($won ? 1 : 0) - $expected));
        if ($won) {
            $delta = max(5, min(50, $delta));
        } else {
            $delta = min(-5, max(-50, $delta));
        }

        $isSelfBattle = $enemyChar && $enemyChar->id === $char->id;
        $trophyBefore = (int)$assignment->trophy;
        $enemyTrophyBefore = $enemyAssignment ? (int)$enemyAssignment->trophy : 0;

        if ($isSelfBattle) {
            $delta = (int)round($delta / 2);
        }

        $assignment->trophy = max(0, $trophyBefore + $delta);
        $assignment->save();

        if ($enemyAssignment && !$isSelfBattle) {
            $enemyAssignment->trophy = max(0, $enemyTrophyBefore - $delta);
            $enemyAssignment->save();
        }

        ShadowWarBattle::create([
            'season_id' => $activeSeason->id,
            'battle_code' => $battleCode,
            'character_id' => $char->id,
            'enemy_id' => $enemyChar?->id,
            'character_squad' => (int)$assignment->squad,
            'enemy_squad' => $enemyAssignment?->squad,
            'character_level' => $char->level,
            'enemy_level' => $enemyChar?->level ?? 0,
            'character_rank' => $assignment->rank,
            'enemy_rank' => $enemyAssignment?->rank ?? 0,
            'character_trophy_before' => $trophyBefore,
            'enemy_trophy_before' => $enemyTrophyBefore,
            'character_trophy_after' => (int)$assignment->trophy,
            'enemy_trophy_after' => $enemyAssignment?->trophy ?? 0,
            'trophy_delta' => $delta,
            'total_damage' => $totalDamage,
            'won' => $won,
            'battle_data' => $this->safeDecodeBattleData($battleData),
            'energy_cost' => 10,
            'energy_before' => (int)($cached['energy_before'] ?? 0),
            'energy_after' => (int)($cached['energy_after'] ?? (int)$assignment->energy),
            'refills_used_today' => (int)$assignment->energy_refills_today,
        ]);

        Cache::forget("shadowwar_battle_$charId");

        return [
            'status' => 1,
            'error' => 0,
            'result' => [0, 0, []],
            'level' => (int)$char->level,
            'xp' => (int)$char->xp,
            'level_up' => false,
            'trophies_got' => $delta,
        ];
    }

    /**
     * getEnemyInfo
     * Params: [charId, sessionKey, targetId]
     *
     * Returns full character data for the target with their active ShadowWar
     * defender preset overriding the outfit/skills so battles use preset
     * loadouts rather than current equipment.
     */
    public function getEnemyInfo($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId     = $params[0] ?? null;
        $sessionKey = $params[1] ?? null;
        $targetId   = (int)($params[2] ?? 0);
        Log::info("AMF ShadowWar.getEnemyInfo: Char $charId Target $targetId");

        // Get base character data (stats, points, clan, etc.)
        $infoService = new InfoService();
        $data = $infoService->getInfo($charId, $sessionKey, $targetId, 'shadowwar');

        if (($data['status'] ?? 0) !== 1) {
            return $data;
        }

        // Override character_sets with the target's active defender preset
        $presetService = new PresetService();
        $preset = $presetService->getActivePreset($targetId);

        if ($preset) {
            $data['character_sets']['weapon']     = $preset['weapon']     ?? $data['character_sets']['weapon'];
            $data['character_sets']['back_item']  = $preset['back_item']  ?? $data['character_sets']['back_item'];
            $data['character_sets']['clothing']   = $preset['clothing']   ?? $data['character_sets']['clothing'];
            $data['character_sets']['hairstyle']  = $preset['hair']       ?? $data['character_sets']['hairstyle'];
            $data['character_sets']['accessory']  = $preset['accessory']  ?? $data['character_sets']['accessory'];
            $data['character_sets']['hair_color'] = $preset['hair_color'] ?? $data['character_sets']['hair_color'];
            $data['character_sets']['skin_color'] = $preset['skin_color'] ?? $data['character_sets']['skin_color'];
            $data['character_sets']['skills']     = $preset['skills']     ?? $data['character_sets']['skills'];

            // Replace pet_data with preset pet if one is configured
            $petInstanceId = (int)($preset['pet']['pet_id'] ?? 0);
            if ($petInstanceId) {
                $petInstance = CharacterPet::where('character_id', $targetId)
                    ->where('id', $petInstanceId)
                    ->first();
                if ($petInstance) {
                    $petConfig = Pet::where('pet_id', $petInstance->pet_id)->first();
                    $data['pet_data'] = [
                        'pet'        => $petInstance->id,
                        'char_id'    => $targetId,
                        'pet_id'     => $petInstance->id,
                        'pet_name'   => $petInstance->name ?: ($petConfig->name ?? 'Pet'),
                        'pet_xp'     => $petInstance->xp,
                        'pet_level'  => $petInstance->level,
                        'pet_skills' => $petConfig
                            ? $petConfig->calculateSkillsString($petInstance->level)
                            : '1,1,1,1,1,1',
                        'pet_mp'     => 0,
                        'pet_swf'    => $petConfig->swf ?? ('pet_' . $petInstance->pet_id),
                    ];
                }
            }
        }

        return $data;
    }

    private function buildEnemies(?int $charId): array
    {
        $query = Character::query()->where('level', '>=', 60);
        if ($charId) {
            $query->where('id', '!=', $charId);
        }

        $characters = $query->inRandomOrder()->limit(5)->get();
        if ($characters->isEmpty() && $charId) {
            $self = Character::find($charId);
            if ($self) {
                $characters = collect([$self]);
            }
        }

        $enemies = [];
        foreach ($characters as $enemy) {
            $genderSuffix = $enemy->gender == 0 ? '_0' : '_1';
            $hair = is_numeric($enemy->hair_style)
                ? 'hair_' . str_pad($enemy->hair_style, 2, '0', STR_PAD_LEFT) . $genderSuffix
                : ($enemy->hair_style ?: 'hair_01' . $genderSuffix);

            $skills = [];
            if ($enemy->equipment_skills) {
                $skills = array_values(array_filter(explode(',', $enemy->equipment_skills)));
            }

            $enemies[] = [
                'id' => $enemy->id,
                'squad' => $enemy->id % 5,
                'rank' => 0,
                'set' => [
                    'weapon' => $enemy->equipment_weapon ?: 'wpn_01',
                    'back_item' => $enemy->equipment_back ?: 'back_01',
                    'clothing' => $enemy->equipment_clothing ?: 'set_01' . $genderSuffix,
                    'hairstyle' => $hair,
                    'accessory' => $enemy->equipment_accessory ?: 'accessory_01',
                    'face' => 'face_02' . $genderSuffix,
                    'hair_color' => $enemy->hair_color ?: 'null|null',
                    'skin_color' => $enemy->skin_color ?: '0|0',
                    'skills' => $skills,
                ],
            ];
        }

        return $enemies;
    }

    private function calculateStrength(int $trophy, int $level, int $rank): float
    {
        $trophyScore = max(0, $trophy);
        $levelScore = max(0, $level) * 25;
        $rankBonus = max(0, (int)round(1200 - ($rank * 1.2)));
        return $trophyScore + $levelScore + $rankBonus;
    }

    private function safeDecodeBattleData($battleData): ?array
    {
        if (!is_string($battleData) || $battleData === '') {
            return null;
        }
        $decoded = json_decode(base64_decode($battleData), true);
        if (!is_array($decoded)) {
            return null;
        }
        return $decoded;
    }
}