<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\User;

class FriendData
{
    public const FRIENDS_PER_PAGE = 8;
    public const REQUESTS_PER_PAGE = 4;
    public const RECOMMENDATIONS_PER_PAGE = 5;
    public const FREE_FRIEND_LIMIT = 50;
    public const PREMIUM_FRIEND_LIMIT = 100;
    public const FRIENDSHIP_KUNAI = 'material_1002';

    public static function buildFriendPayload(Character $friend): array
    {
        $genderSuffix = ($friend->gender == 0 ? '_0' : '_1');
        if (is_numeric($friend->hair_style)) {
            $hairstyle = 'hair_' . str_pad($friend->hair_style, 2, '0', STR_PAD_LEFT) . $genderSuffix;
        } else {
            $hairstyle = $friend->hair_style ?: 'hair_01' . $genderSuffix;
        }

        return [
            'id' => $friend->id,
            'account_type' => $friend->user->account_type ?? 0,
            'char' => [
                'name' => $friend->name,
                'level' => $friend->level,
                'rank' => $friend->rank,
            ],
            'sets' => [
                'hairstyle' => $hairstyle,
                'face' => 'face_01' . $genderSuffix,
                'hair_color' => $friend->hair_color ?: '0|0',
                'skin_color' => $friend->skin_color ?: '0|0',
            ],
        ];
    }

    public static function getFriendLimit(?User $user): int
    {
        $accountType = $user->account_type ?? 0;
        return $accountType >= 1 ? self::PREMIUM_FRIEND_LIMIT : self::FREE_FRIEND_LIMIT;
    }
}
