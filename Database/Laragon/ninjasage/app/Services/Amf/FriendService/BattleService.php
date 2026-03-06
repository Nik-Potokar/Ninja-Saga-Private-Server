<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Models\CharacterItem;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BattleService
{
    use ValidatesSession;

    /**
     * startBerantem
     * Params: [charId, friendId, hash, sessionKey]
     */
    public function startBerantem($charId, $friendId, $hash, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.startBerantem: Char $charId Friend $friendId");

        $expectedHash = hash('sha256', $charId . $friendId . $sessionKey);
        if ($hash !== $expectedHash) {
            return ['status' => 2, 'result' => 'Invalid request.'];
        }

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friend = Character::find($friendId);
        if (!$friend) return ['status' => 2, 'result' => 'Character not found'];

        $isFriend = CharacterFriend::where('character_id', $charId)->where('friend_id', $friendId)->exists();
        if (!$isFriend) {
            return ['status' => 2, 'result' => 'You can only battle friends.'];
        }

        $battleCode = Str::random(10);
        Cache::put("friend_battle_$charId", [
            'battle_code' => $battleCode,
            'friend_id' => (int)$friendId,
            'started_at' => now(),
        ], 1800);

        return [
            'status' => 1,
            'battle_code' => $battleCode,
            'friend_id' => (int)$friendId,
        ];
    }

    /**
     * endBerantem
     * Params: [charId, battleCode, hash, sessionKey, battleData]
     */
    public function endBerantem($charId, $battleCode, $hash, $sessionKey, $battleData)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.endBerantem: Char $charId Battle $battleCode");

        $expectedHash = hash('sha256', $charId . $battleCode . $sessionKey);
        if ($hash !== $expectedHash) {
            return ['status' => 2, 'result' => 'Invalid request.'];
        }

        $cached = Cache::get("friend_battle_$charId");
        if (!$cached || ($cached['battle_code'] ?? null) !== $battleCode) {
            return ['status' => 2, 'result' => 'Battle not found.'];
        }

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $rewardItem = FriendData::FRIENDSHIP_KUNAI;
        $rewardQuantity = 1;
        $rewardString = $rewardItem . ':' . $rewardQuantity;

        $charItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', $rewardItem)
            ->first();

        if ($charItem) {
            $charItem->quantity += $rewardQuantity;
            $charItem->save();
        } else {
            CharacterItem::create([
                'character_id' => $charId,
                'item_id' => $rewardItem,
                'quantity' => $rewardQuantity,
                'category' => 'material',
            ]);
        }

        Cache::forget("friend_battle_$charId");

        return [
            'status' => 1,
            'result' => [0, 0, [$rewardString]],
            'xp' => (int)$char->xp,
            'level' => (int)$char->level,
            'level_up' => false,
        ];
    }
}
