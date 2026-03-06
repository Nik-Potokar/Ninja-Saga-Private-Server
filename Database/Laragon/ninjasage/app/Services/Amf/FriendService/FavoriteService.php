<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class FavoriteService
{
    use ValidatesSession;

    /**
     * setFavorite
     * Params: [charId, sessionKey, friendId]
     */
    public function setFavorite($charId, $sessionKey, $friendId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.setFavorite: Char $charId Friend $friendId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friendLink = CharacterFriend::where('character_id', $charId)->where('friend_id', $friendId)->first();
        if (!$friendLink) {
            return ['status' => 2, 'result' => 'Friend not found.'];
        }

        $friendLink->is_favorite = true;
        $friendLink->save();

        return ['status' => 1, 'result' => 'Favorite added.'];
    }

    /**
     * removeFavorite
     * Params: [charId, sessionKey, friendId]
     */
    public function removeFavorite($charId, $sessionKey, $friendId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.removeFavorite: Char $charId Friend $friendId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friendLink = CharacterFriend::where('character_id', $charId)->where('friend_id', $friendId)->first();
        if (!$friendLink) {
            return ['status' => 2, 'result' => 'Friend not found.'];
        }

        $friendLink->is_favorite = false;
        $friendLink->save();

        return ['status' => 1, 'result' => 'Favorite removed.'];
    }
}
