<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Models\FriendRequest;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestsService
{
    use ValidatesSession;

    /**
     * addFriend
     * Params: [charId, sessionKey, friendId]
     */
    public function addFriend($charId, $sessionKey, $friendId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.addFriend: Char $charId Friend $friendId");

        if ($charId == $friendId) {
            return ['status' => 2, 'result' => 'You cannot add yourself.'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friend = Character::find($friendId);
        if (!$friend) return ['status' => 2, 'result' => 'Character not found'];

        $limit = FriendData::getFriendLimit($char->user);
        $currentCount = CharacterFriend::where('character_id', $charId)->count();
        if ($currentCount >= $limit) {
            return ['status' => 2, 'result' => 'Friend list is full.'];
        }

        if (CharacterFriend::where('character_id', $charId)->where('friend_id', $friendId)->exists()) {
            return ['status' => 2, 'result' => 'Already friends.'];
        }

        $incoming = FriendRequest::where('character_id', $charId)->where('requester_id', $friendId)->first();
        if ($incoming) {
            return $this->acceptFriend($charId, $sessionKey, $friendId);
        }

        $existing = FriendRequest::where('character_id', $friendId)->where('requester_id', $charId)->exists();
        if ($existing) {
            return ['status' => 2, 'result' => 'Friend request already sent.'];
        }

        FriendRequest::create([
            'character_id' => $friendId,
            'requester_id' => $charId,
        ]);

        return ['status' => 1, 'result' => 'Friend request sent.'];
    }

    /**
     * friendRequests
     * Params: [charId, sessionKey, page]
     */
    public function friendRequests($charId, $sessionKey, $page = 1)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.friendRequests: Char $charId Page $page");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $page = max(1, (int)$page);
        $query = FriendRequest::where('character_id', $charId)->orderByDesc('created_at');
        $total = $query->count();
        $totalPages = max(1, (int)ceil($total / FriendData::REQUESTS_PER_PAGE));
        $requests = $query->skip(($page - 1) * FriendData::REQUESTS_PER_PAGE)
            ->take(FriendData::REQUESTS_PER_PAGE)
            ->get();

        $invitations = [];
        foreach ($requests as $request) {
            $requester = Character::with('user')->find($request->requester_id);
            if ($requester) {
                $invitations[] = FriendData::buildFriendPayload($requester);
            }
        }

        return [
            'status' => 1,
            'invitations' => $invitations,
            'total' => $total,
            'page' => [
                'current' => $page,
                'total' => $totalPages,
            ],
        ];
    }

    /**
     * acceptFriend
     * Params: [charId, sessionKey, friendId]
     */
    public function acceptFriend($charId, $sessionKey, $friendId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.acceptFriend: Char $charId Friend $friendId");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $friend = Character::find($friendId);
        if (!$friend) return ['status' => 2, 'result' => 'Character not found'];

        $limit = FriendData::getFriendLimit($char->user);
        $currentCount = CharacterFriend::where('character_id', $charId)->count();
        if ($currentCount >= $limit) {
            return ['status' => 2, 'result' => 'Friend list is full.'];
        }

        $request = FriendRequest::where('character_id', $charId)
            ->where('requester_id', $friendId)
            ->first();

        if (!$request) {
            return ['status' => 2, 'result' => 'Friend request not found.'];
        }

        DB::transaction(function () use ($charId, $friendId, $request) {
            CharacterFriend::firstOrCreate([
                'character_id' => $charId,
                'friend_id' => $friendId,
            ]);
            CharacterFriend::firstOrCreate([
                'character_id' => $friendId,
                'friend_id' => $charId,
            ]);

            $request->delete();
        });

        return ['status' => 1, 'result' => 'Friend request accepted.'];
    }

    /**
     * acceptAll
     * Params: [charId, sessionKey]
     */
    public function acceptAll($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.acceptAll: Char $charId");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $requests = FriendRequest::where('character_id', $charId)->orderByDesc('created_at')->get();
        if ($requests->isEmpty()) {
            return ['status' => 1, 'result' => 'No friend requests to accept.'];
        }

        $limit = FriendData::getFriendLimit($char->user);
        $currentCount = CharacterFriend::where('character_id', $charId)->count();
        $capacity = max(0, $limit - $currentCount);

        if ($capacity <= 0) {
            return ['status' => 2, 'result' => 'Friend list is full.'];
        }

        $accepted = 0;
        DB::transaction(function () use ($charId, $requests, $capacity, &$accepted) {
            foreach ($requests as $request) {
                if ($accepted >= $capacity) {
                    break;
                }

                $friendId = $request->requester_id;
                CharacterFriend::firstOrCreate([
                    'character_id' => $charId,
                    'friend_id' => $friendId,
                ]);
                CharacterFriend::firstOrCreate([
                    'character_id' => $friendId,
                    'friend_id' => $charId,
                ]);

                $request->delete();
                $accepted++;
            }
        });

        return ['status' => 1, 'result' => 'Friend requests accepted.'];
    }

    /**
     * removeAll
     * Params: [charId, sessionKey]
     */
    public function removeAll($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.removeAll: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        FriendRequest::where('character_id', $charId)->delete();

        return ['status' => 1, 'result' => 'Friend requests removed.'];
    }
}
