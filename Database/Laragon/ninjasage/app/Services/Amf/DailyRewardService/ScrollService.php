<?php

namespace App\Services\Amf\DailyRewardService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScrollService
{
    use ValidatesSession;

    /**
     * claimScrollOfWisdom
     * Params: [charId, sessionKey]
     */
    public function claimScrollOfWisdom($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyReward.claimScrollOfWisdom: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        if ($char->level < 80) {
            return ['status' => 2, 'result' => 'You must be Level 80 to claim this reward!'];
        }

        if ($char->daily_scroll_claimed_at) {
            return ['status' => 2, 'result' => 'Already claimed!'];
        }

        $itemId = 'essential_10';
        $charItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', $itemId)
            ->first();

        if ($charItem) {
            $charItem->quantity += 1;
            $charItem->save();
        } else {
            CharacterItem::create([
                'character_id' => $charId,
                'item_id' => $itemId,
                'quantity' => 1,
                'category' => 'essential'
            ]);
        }

        $char->daily_scroll_claimed_at = now();
        $char->save();

        return ['status' => 1];
    }
}
