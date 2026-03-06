<?php

namespace App\Services\Amf\EudemonGardenService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\GameConfig;
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

    /**
     * startHunting
     * Params: [charId, bossNum, sessionKey]
     */
    public function startHunting($charId, $bossNum, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF EudemonGarden.startHunting: Char $charId Boss $bossNum");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $tries = explode(',', $char->eudemon_garden_tries);
        if (!isset($tries[$bossNum]) || $tries[$bossNum] <= 0) {
            return ['status' => 2, 'result' => 'No tries left!'];
        }

        $bosses = GameConfig::get('eudemon')['bosses'] ?? [];
        $boss = $bosses[$bossNum] ?? null;
        if (!$boss) return ['status' => 0, 'error' => 'Boss not found'];

        if ($char->level < $boss['lvl']) {
            return ['status' => 2, 'result' => 'Level too low!'];
        }

        $battleCode = Str::random(10);

        // Deduct try
        $tries[$bossNum]--;
        $char->eudemon_garden_tries = implode(',', $tries);
        $char->save();

        // client hash: this.__hash(Character.char_id + Character.battle_code + this.boss_num)
        $hash = hash('sha256', $charId . $battleCode . $bossNum);

        Cache::put("eudemon_battle_$charId", [
            'code' => $battleCode,
            'boss_num' => $bossNum,
            'boss_id' => $boss['id'],
            'reward_xp' => (int)$boss['xp'],
            'reward_gold' => (int)$boss['gold'],
            'rewards' => $boss['rewards']
        ], 1800);

        return [
            'status' => 1,
            'error' => 0,
            'code' => $battleCode,
            'boss' => 'boss_' . $bossNum,
            'hash' => $hash
        ];
    }

    /**
     * finishHunting
     * Params: [charId, bossNum, battleCode, hash, sessionKey, unknown]
     */
    public function finishHunting($charId, $bossNum, $battleCode, $hash, $sessionKey, $unknown)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF EudemonGarden.finishHunting: Char $charId Boss $bossNum");

        $cached = Cache::get("eudemon_battle_$charId");
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
        $rewardConfigs = $cached['rewards'] ?? [];
        $awardedItemsMap = [];

        foreach ($rewardConfigs as $rewardConf) {
            // Handle legacy simple string format
            if (is_string($rewardConf)) {
                $awardedItemsMap[$rewardConf] = ($awardedItemsMap[$rewardConf] ?? 0) + 1;
                continue;
            }

            // Handle new object format
            $itemId = $rewardConf['id'];
            $rate = $rewardConf['rate'] ?? 100;
            $min = $rewardConf['min'] ?? 1;
            $max = $rewardConf['max'] ?? 1;
            $unique = $rewardConf['unique'] ?? false;
            $ownedRate = $rewardConf['owned_rate'] ?? 0;

            if ($unique) {
                $isOwned = CharacterItem::where('character_id', $charId)->where('item_id', $itemId)->exists();
                if ($isOwned) {
                    $rate = $ownedRate;
                }
            }

            $roll = mt_rand(1, 10000);
            $threshold = $rate * 100;

            if ($roll <= $threshold) {
                $qty = mt_rand($min, $max);
                $awardedItemsMap[$itemId] = ($awardedItemsMap[$itemId] ?? 0) + $qty;
            }
        }

        $awardedItemsList = [];
        foreach ($awardedItemsMap as $itemId => $qty) {
            $this->grantItem($charId, $itemId, $qty);
            $awardedItemsList[] = ($qty > 1) ? $itemId . ':' . $qty : $itemId;
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

        Cache::forget("eudemon_battle_$charId");

        return [
            'status' => 1,
            'error' => 0,
            'result' => [
                (int)$xpReward,
                (int)$goldReward,
                $awardedItemsList
            ],
            'level' => (int)$char->level,
            'xp' => (int)$char->xp,
            'level_up' => $levelUp,
            'account_tokens' => (int)$char->user->tokens
        ];
    }

    private function grantItem($charId, $itemId, $qty)
    {
        $itemConfig = Item::where('item_id', $itemId)->first();
        $cat = $itemConfig->category ?? 'material';

        $invItem = CharacterItem::firstOrCreate(
            ['character_id' => $charId, 'item_id' => $itemId],
            ['quantity' => 0, 'category' => $cat]
        );
        $invItem->quantity += $qty;
        $invItem->save();
    }
}