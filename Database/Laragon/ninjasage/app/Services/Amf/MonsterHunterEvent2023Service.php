<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterEventData;
use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MonsterHunterEvent2023Service
{
    use ValidatesSession;

    private const EVENT_KEY   = 'monster_hunter';
    private const PANEL       = 'MonsterHunter';
    private const ENERGY_MAX  = 100;
    private const ENERGY_COST = 20;

    // -------------------------------------------------------------------------

    public function getEventData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MonsterHunterEvent2023.getEventData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Monster Hunter event is currently inactive.'];
        }

        $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        return [
            'status'  => 1,
            'error'   => 0,
            'energy'  => $eventData->energy,
            'boss_id' => $config['boss_id'] ?? 'enemy_monster_1',
        ];
    }

    // -------------------------------------------------------------------------

    public function startBattle($charId, $bossId, $hash, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MonsterHunterEvent2023.startBattle: Char $charId Boss $bossId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Monster Hunter event is currently inactive.'];
        }

        $energyMax  = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
        $energyCost = (int) ($config['energy_cost'] ?? self::ENERGY_COST);
        $eventData  = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax);

        if ($eventData->energy < $energyCost) {
            return ['status' => 2, 'result' => "Not enough energy (need {$energyCost}, have {$eventData->energy})."];
        }

        // Validate client hash: sha256(charId + bossId)
        $expectedHash = hash('sha256', $charId . $bossId);
        if (!hash_equals($expectedHash, (string) $hash)) {
            Log::warning("MonsterHunter startBattle hash mismatch for Char $charId");
            return ['status' => 2, 'result' => 'Security check failed.'];
        }

        $code = Str::random(16);

        $eventData->energy -= $energyCost;
        $eventData->save();

        Cache::put("mh_battle_{$charId}", [
            'code'    => $code,
            'boss_id' => $bossId,
            'config'  => $config,
        ], 1800);

        // Server hash verified by client: sha256(bossId + code + charId)
        $responseHash = hash('sha256', $bossId . $code . $charId);

        return [
            'status' => 1,
            'error'  => 0,
            'code'   => $code,
            'hash'   => $responseHash,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Battle.as params: charId, bossId, battleCode, totalDamage, hash, win, sessionKey
     */
    public function finishBattle($charId, $bossId, $battleCode, $totalDamage, $hash, $win, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MonsterHunterEvent2023.finishBattle: Char $charId Win $win");

        $cached = Cache::get("mh_battle_{$charId}");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 2, 'result' => 'Invalid battle session.'];
        }
        Cache::forget("mh_battle_{$charId}");

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $config  = $cached['config'];
        $rewards = $config['rewards'] ?? [];
        $granted = [];

        if ((int) $win === 1) {
            $granter = new RewardGrantService();
            $levelUp = false;

            foreach ($rewards as $rewardStr) {
                $levelUp = $granter->grant($char, (string) $rewardStr) || $levelUp;
                $granted[] = $rewardStr;
            }

            $energyMax = (int) ($config['energy_max'] ?? self::ENERGY_MAX);
            CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, $energyMax)->increment('battles');
            $char->refresh();

            return [
                'status'   => 1,
                'error'    => 0,
                'result'   => ['0', '0', $granted],
                'level'    => (int) $char->level,
                'xp'       => (int) $char->xp,
                'level_up' => $levelUp,
            ];
        }

        return [
            'status'   => 1,
            'error'    => 0,
            'result'   => ['0', '0', []],
            'level'    => (int) $char->level,
            'xp'       => (int) $char->xp,
            'level_up' => false,
        ];
    }

    // -------------------------------------------------------------------------

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;
        return $event->data ?? [];
    }
}