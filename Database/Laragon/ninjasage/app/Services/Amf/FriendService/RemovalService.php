<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Models\FriendRequest;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemovalService
{
    use ValidatesSession;

    /**
     * removeFriend
     * Params: [charId, sessionKey, friendId|friendIds[]]
     */
    public function removeFriend($charId, $sessionKey, $friendIdOrIds)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.removeFriend: Char $charId Friend $friendIdOrIds");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friendIds = is_array($friendIdOrIds) ? $friendIdOrIds : [$friendIdOrIds];

        DB::transaction(function () use ($charId, $friendIds) {
            foreach ($friendIds as $friendId) {
                CharacterFriend::where('character_id', $charId)
                    ->where('friend_id', $friendId)
                    ->delete();
                CharacterFriend::where('character_id', $friendId)
                    ->where('friend_id', $charId)
                    ->delete();
                FriendRequest::where(function ($query) use ($charId, $friendId) {
                    $query->where('character_id', $charId)
                        ->where('requester_id', $friendId);
                })->orWhere(function ($query) use ($charId, $friendId) {
                    $query->where('character_id', $friendId)
                        ->where('requester_id', $charId);
                })->delete();
            }
        });

        return ['status' => 1, 'result' => 'Friend removed.'];
    }

    /**
     * unfriendAll
     * Params: [charId, sessionKey]
     */
    public function unfriendAll($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.unfriendAll: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        CharacterFriend::where('character_id', $charId)
            ->orWhere('friend_id', $charId)
            ->delete();

        return ['status' => 1, 'result' => 'All friends removed.'];
    }
}
