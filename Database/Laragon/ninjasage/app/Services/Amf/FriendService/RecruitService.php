<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Models\CharacterRecruit;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class RecruitService
{
    use ValidatesSession;

    /**
     * recruitable
     * Params: [charId, sessionKey]
     */
    public function recruitable($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.recruitable: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $char->recruitable = !$char->recruitable;
        $char->save();

        return [
            'status' => 1,
            'recruitable' => (bool)$char->recruitable,
        ];
    }

    /**
     * recruitFriend
     * Params: [charId, sessionKey, friendId]
     */
    public function recruitFriend($charId, $sessionKey, $friendId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.recruitFriend: Char $charId Friend $friendId");

        if ($charId == $friendId) {
            return ['status' => 2, 'result' => 'You cannot recruit yourself.'];
        }

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friend = Character::find($friendId);
        if (!$friend) return ['status' => 2, 'result' => 'Character not found'];

        if (($friend->level - $char->level) > 10) {
            return [
                'status' => 2,
                'error' => 0,
                'result' => 'You can only recruit a friend with level not higher than 10 from your level'
            ];
        }

        if (!$friend->recruitable) {
            return ['status' => 2, 'result' => 'This friend is not recruitable.'];
        }

        $isFriend = CharacterFriend::where('character_id', $charId)->where('friend_id', $friendId)->exists();
        if (!$isFriend) {
            return ['status' => 2, 'result' => 'You can only recruit friends.'];
        }

        $recruitId = 'char_' . $friendId;

        if (CharacterRecruit::where('character_id', $charId)->where('recruit_id', $recruitId)->exists()
            || CharacterRecruit::where('character_id', $charId)->where('recruit_id', (string)$friendId)->exists()) {
            return ['status' => 2, 'result' => 'Friend already recruited.'];
        }

        if (CharacterRecruit::where('character_id', $charId)->count() >= 2) {
            return ['status' => 2, 'result' => 'You cannot recruit more teammate'];
        }

        CharacterRecruit::create([
            'character_id' => $charId,
            'recruit_id' => $recruitId,
        ]);

        $recruits = CharacterRecruit::where('character_id', $charId)->pluck('recruit_id')->toArray();
        if (empty($recruits)) {
            $recruits = [$recruitId];
        }

        $firstId = (string)$recruits[0];
        $hash = hash('sha256', $firstId);

        return [
            'status' => 1,
            'error' => 0,
            'recruiters' => [
                $recruits,
                $hash
            ]
        ];
    }
}
