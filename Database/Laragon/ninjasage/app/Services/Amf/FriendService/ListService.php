<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Models\FriendRequest;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class ListService
{
    use ValidatesSession;

    /**
     * friends
     * Params: [charId, sessionKey, page]
     */
    public function friends($charId, $sessionKey, $page = 1)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.friends: Char $charId Page $page");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $page = max(1, (int)$page);
        $query = CharacterFriend::where('character_id', $charId)->orderByDesc('created_at');
        $total = $query->count();
        $totalPages = max(1, (int)ceil($total / FriendData::FRIENDS_PER_PAGE));

        $friends = $query->skip(($page - 1) * FriendData::FRIENDS_PER_PAGE)
            ->take(FriendData::FRIENDS_PER_PAGE)
            ->get();

        $friendPayloads = [];
        foreach ($friends as $friendLink) {
            $friend = Character::with('user')->find($friendLink->friend_id);
            if ($friend) {
                $friendPayloads[] = FriendData::buildFriendPayload($friend);
            }
        }

        return [
            'status' => 1,
            'friends' => $friendPayloads,
            'total' => $total,
            'limit' => FriendData::getFriendLimit($char->user),
            'recruitable' => $char->recruitable ?? true,
            'page' => [
                'current' => $page,
                'total' => $totalPages,
            ],
        ];
    }

    /**
     * getFavorite
     * Params: [charId, sessionKey, page]
     */
    public function getFavorite($charId, $sessionKey, $page = 1)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.getFavorite: Char $charId Page $page");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $page = max(1, (int)$page);
        $query = CharacterFriend::where('character_id', $charId)
            ->where('is_favorite', true)
            ->orderByDesc('created_at');
        $total = $query->count();
        $totalPages = max(1, (int)ceil($total / FriendData::FRIENDS_PER_PAGE));

        $friends = $query->skip(($page - 1) * FriendData::FRIENDS_PER_PAGE)
            ->take(FriendData::FRIENDS_PER_PAGE)
            ->get();

        $friendPayloads = [];
        foreach ($friends as $friendLink) {
            $friend = Character::with('user')->find($friendLink->friend_id);
            if ($friend) {
                $friendPayloads[] = FriendData::buildFriendPayload($friend);
            }
        }

        return [
            'status' => 1,
            'friends' => $friendPayloads,
            'total' => $total,
            'limit' => FriendData::getFriendLimit($char->user),
            'recruitable' => $char->recruitable ?? true,
            'page' => [
                'current' => $page,
                'total' => $totalPages,
            ],
        ];
    }

    /**
     * search
     * Params: [charId, sessionKey, query]
     */
    public function search($charId, $sessionKey, $query)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.search: Char $charId Query $query");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $search = trim((string)$query);
        if ($search === '') {
            return $this->friends($charId, $sessionKey, 1);
        }

        $friendIds = CharacterFriend::where('character_id', $charId)->pluck('friend_id')->toArray();
        $friendQuery = Character::with('user')->whereIn('id', $friendIds);

        if (is_numeric($search)) {
            $friendQuery->where('id', (int)$search);
        } else {
            $friendQuery->where('name', 'like', '%' . $search . '%');
        }

        $total = $friendQuery->count();
        $totalPages = max(1, (int)ceil($total / FriendData::FRIENDS_PER_PAGE));
        $friends = $friendQuery->orderBy('id')
            ->take(FriendData::FRIENDS_PER_PAGE)
            ->get();

        $friendPayloads = [];
        foreach ($friends as $friend) {
            $friendPayloads[] = FriendData::buildFriendPayload($friend);
        }

        return [
            'status' => 1,
            'friends' => $friendPayloads,
            'total' => $total,
            'limit' => FriendData::getFriendLimit($char->user),
            'recruitable' => $char->recruitable ?? true,
            'page' => [
                'current' => 1,
                'total' => $totalPages,
            ],
        ];
    }

    /**
     * getRecommendations
     * Params: [charId, sessionKey, page]
     */
    public function getRecommendations($charId, $sessionKey, $page = 1)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.getRecommendations: Char $charId Page $page");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $page = max(1, (int)$page);

        $friendIds = CharacterFriend::where('character_id', $charId)->pluck('friend_id')->toArray();
        $outgoingIds = FriendRequest::where('requester_id', $charId)->pluck('character_id')->toArray();
        $incomingIds = FriendRequest::where('character_id', $charId)->pluck('requester_id')->toArray();
        $excludeIds = array_unique(array_merge([$charId], $friendIds, $outgoingIds, $incomingIds));

        $query = Character::with('user')->whereNotIn('id', $excludeIds)->orderByDesc('id');
        $total = $query->count();
        $totalPages = max(1, (int)ceil($total / FriendData::RECOMMENDATIONS_PER_PAGE));
        $recommendations = $query->skip(($page - 1) * FriendData::RECOMMENDATIONS_PER_PAGE)
            ->take(FriendData::RECOMMENDATIONS_PER_PAGE)
            ->get();

        $payloads = [];
        foreach ($recommendations as $friend) {
            $payloads[] = FriendData::buildFriendPayload($friend);
        }

        return [
            'status' => 1,
            'recommendations' => $payloads,
            'page' => [
                'current' => $page,
                'total' => $totalPages,
            ],
        ];
    }
}
