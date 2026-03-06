<?php

namespace App\Services\Amf\BattleSystemService;

use App\Models\Character;
use App\Models\Enemy;
use App\Services\Amf\SessionValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StartService
{
    /**
     * startMission
     */
    public function startMission($charId, $missionId, $enemyId, $enemyStats, $unknown, $hash, $sessionKey)
    {
        Log::info("AMF Start Mission: Char $charId Mission $missionId");

        $guard = SessionValidator::validateCharacter((int)$charId, $sessionKey);
        if ($guard) return $guard;

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Validate Mission
        $mission = \App\Models\Mission::where('mission_id', $missionId)->first();

        if (!$mission) {
            // Check for padded/unpadded match
            if (str_starts_with($missionId, 'msn_')) {
                $num = intval(substr($missionId, 4));
                $paddedId = 'msn_' . str_pad($num, 2, '0', STR_PAD_LEFT);
                $mission = \App\Models\Mission::where('mission_id', $paddedId)->first();
            }
        }

        if (!$mission) {
            Log::error("Mission data not found for ID: $missionId");
            return ['status' => 0, 'error' => 'Mission data not found'];
        }

        if ($char->level < $mission->req_lvl) {
            return ['status' => 0, 'error' => 'Level too low'];
        }

        $missionDef = $this->getMissionDefinition($missionId);
        if (!$missionDef) {
            return ['status' => 0, 'error' => 'Mission data not found'];
        }

        $expectedEnemies = $missionDef['enemies'] ?? [];
        if (!is_array($expectedEnemies)) {
            $expectedEnemies = [];
        }

        $providedEnemies = array_values(array_filter(explode(',', (string)$enemyId), 'strlen'));
        if (!empty($expectedEnemies)) {
            if (count($expectedEnemies) !== count($providedEnemies)) {
                return ['status' => 0, 'error' => 'Invalid enemies'];
            }

            foreach ($expectedEnemies as $index => $expectedId) {
                if (!isset($providedEnemies[$index]) || $providedEnemies[$index] !== $expectedId) {
                    return ['status' => 0, 'error' => 'Invalid enemies'];
                }
            }

            $statsMap = $this->parseEnemyStats((string)$enemyStats);
            foreach ($expectedEnemies as $expectedId) {
                $stats = $statsMap[$expectedId] ?? null;
                if (!$stats) {
                    return ['status' => 0, 'error' => 'Invalid enemy stats'];
                }

                $enemy = Enemy::where('enemy_id', $expectedId)->first();
                if (!$enemy) {
                    return ['status' => 0, 'error' => 'Invalid enemy'];
                }

                if ((int)$stats['hp'] !== (int)$enemy->hp || (int)$stats['agility'] !== (int)$enemy->agility) {
                    return ['status' => 0, 'error' => 'Invalid enemy stats'];
                }
            }

            if (!$this->validateHash((string)$hash, (string)$enemyId . (string)$enemyStats . (string)$unknown)) {
                return ['status' => 0, 'error' => 'Invalid mission data'];
            }
        }

        $token = Str::random(10);

        Log::info("Mission Started: $missionId. Rewards: Gold={$mission->gold}, XP={$mission->xp}");

        // Store mission data in cache
        Cache::put("battle_token_$charId", [
            'token' => $token,
            'mission_id' => $mission->mission_id ?? $missionId,
            'reward_xp' => (int)$mission->xp,
            'reward_gold' => (int)$mission->gold,
            'start_time' => now()
        ], 1800);

        return $token;
    }

    private function getMissionDefinition(string $missionId): ?array
    {
        $missions = Cache::remember('mission_definitions_map', 3600, function () {
            $path = base_path('public/game_data/mission.json');
            if (!File::exists($path)) {
                return [];
            }

            $list = json_decode(File::get($path), true);
            if (!is_array($list)) {
                return [];
            }

            $map = [];
            foreach ($list as $entry) {
                if (!is_array($entry) || !isset($entry['id'])) {
                    continue;
                }

                $id = (string)$entry['id'];
                $map[$id] = $entry;

                if (preg_match('/^msn_0*(\d+)$/', $id, $matches)) {
                    $num = (int)$matches[1];
                    $normalized = 'msn_' . $num;
                    $padded = 'msn_' . str_pad($num, 2, '0', STR_PAD_LEFT);
                    $map[$normalized] = $entry;
                    $map[$padded] = $entry;
                }
            }

            return $map;
        });

        return $missions[$missionId] ?? null;
    }

    private function parseEnemyStats(string $enemyStats): array
    {
        $result = [];
        $chunks = array_filter(explode('#', $enemyStats), 'strlen');
        foreach ($chunks as $chunk) {
            $parts = explode('|', $chunk);
            $entry = [];
            foreach ($parts as $part) {
                $kv = explode(':', $part, 2);
                if (count($kv) !== 2) {
                    continue;
                }
                $entry[$kv[0]] = $kv[1];
            }

            if (isset($entry['id'])) {
                $result[$entry['id']] = [
                    'hp' => $entry['hp'] ?? null,
                    'agility' => $entry['agility'] ?? null,
                ];
            }
        }

        return $result;
    }

    private function validateHash(string $hash, string $payload): bool
    {
        $hash = trim($hash);
        if ($hash === '') {
            return true;
        }

        return match (strlen($hash)) {
            32 => hash('md5', $payload) === $hash,
            40 => hash('sha1', $payload) === $hash,
            64 => hash('sha256', $payload) === $hash,
            default => true,
        };
    }
}
