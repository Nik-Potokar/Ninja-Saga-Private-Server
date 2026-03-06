<?php

namespace App\Services\Amf\HuntingHouseService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\Item;
use App\Models\XP;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HuntService
{
    use ValidatesSession;

    // Kari Badges (material_509) granted per zone on a successful hunt.
    // Each array entry increments quantity by 1, so duplicates stack correctly.
    // Overridden at runtime by GameConfig['hunting_house_zone_rewards'] if set.
    private const ZONE_REWARDS_DEFAULT = [
        1 => ['material_509'],
        2 => ['material_509', 'material_509'],
        3 => ['material_509'],
        4 => ['material_509', 'material_509', 'material_509'],
        5 => ['material_509', 'material_509', 'material_509', 'material_509'],
    ];

    private function zoneRewards(): array
    {
        $config = \App\Models\GameConfig::get('hunting_house_zone_rewards', null);
        if (is_array($config) && !empty($config)) {
            // GameConfig stores string keys; convert to int-keyed
            $out = [];
            foreach ($config as $zoneId => $rewards) {
                $out[(int) $zoneId] = is_array($rewards) ? $rewards : [];
            }
            return $out;
        }
        return self::ZONE_REWARDS_DEFAULT;
    }

    /**
     * startHunting
     */
    public function startHunting($charId, $zoneId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF HuntingHouse.startHunting: Char $charId Zone $zoneId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Simple implementation for now
        $battleCode = Str::random(10);

        // hash: this.__hash(String(this.currentZone + 1) + String(Character.char_id) + Character.battle_code)
        $hash = hash('sha256', $zoneId . $charId . $battleCode);

        Cache::put("hunting_battle_$charId", [
            'code'        => $battleCode,
            'zone_id'     => (int) $zoneId,
            'reward_xp'   => $char->level * 100,
            'reward_gold'  => $char->level * 200,
            'rewards'     => $this->zoneRewards()[(int) $zoneId] ?? [],
        ], 1800);

        return [
            'status' => 1,
            'error' => 0,
            'code' => $battleCode,
            'boss' => 'ene_' . $zoneId,
            'hash' => $hash
        ];
    }

    /**
     * finishHunting
     */
    public function finishHunting($charId, $zoneId, $battleCode, $hash, $sessionKey, $unknown)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF HuntingHouse.finishHunting: Char $charId Zone $zoneId");

        $cached = Cache::get("hunting_battle_$charId");
        if (!$cached || $cached['code'] !== $battleCode) {
            return ['status' => 0, 'error' => 'Invalid battle session'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $levelUp = false;
        $xpReward = (int)$cached['reward_xp'];
        $goldReward = (int)$cached['reward_gold'];

        // Apply Double XP if active
        if ($char->double_xp_expire_at && Carbon::now()->lt(Carbon::parse($char->double_xp_expire_at))) {
            $rate = $char->xp_bonus_rate ?: 0;
            if ($rate > 0) {
                $xpReward = intval($xpReward * (1 + ($rate / 100)));
            }
        }

        $char->gold += $goldReward;
        $levelUp = $char->addXp($xpReward);

        // Award Items
        $rewardItems = $cached['rewards'] ?? [];
        foreach ($rewardItems as $rewardId) {
            $itemConfig = Item::where('item_id', $rewardId)->first();
            $cat = $itemConfig->category ?? 'material';

            $invItem = CharacterItem::firstOrCreate(
                ['character_id' => $charId, 'item_id' => $rewardId],
                ['quantity' => 0, 'category' => $cat]
            );
            $invItem->increment('quantity');
        }

        // Pet XP Logic
        if ($char->equipment_pet) {
            $activePet = CharacterPet::where('character_id', $charId)
                ->where('id', $char->equipment_pet)
                ->first();

            if ($activePet) {
                $activePet->xp += $xpReward;

                // Pet Level Up
                $maxLevels = 85;
                while ($activePet->level < $maxLevels) {
                    $petXpReq = XP::where('level', $activePet->level)->value('pet_xp');
                    $petRequired = $petXpReq ?: 999999999;

                    if ($activePet->xp >= $petRequired) {
                        $activePet->level++;
                    } else {
                        break;
                    }
                }
                $activePet->save();
            }
        }

        $char->save();

        Cache::forget("hunting_battle_$charId");

        return [
            'status' => 1,
            'error' => 0,
            'result' => [
                (int)$xpReward,
                (int)$goldReward,
                $cached['rewards'] ?? []
            ],
            'level' => (int)$char->level,
            'xp' => (int)$char->xp,
            'level_up' => $levelUp,
            'account_tokens' => (int)$char->user->tokens
        ];
    }
}