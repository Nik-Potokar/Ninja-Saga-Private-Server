<?php

namespace App\Services\Amf\SenjutsuService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class DiscoverService
{
    use ValidatesSession;

    private const REQUIRE_LEVEL = 80;
    private const COSTS = [
        'toad' => ['gold' => 2000000, 'token' => 0],
        'snake' => ['gold' => 2000000, 'token' => 0],
    ];

    /**
     * discoverSenjutsu
     * Params: [charId, sessionKey, type]
     */
    public function discoverSenjutsu($charId, $sessionKey, $type)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        $type = strtolower((string)$type);
        if (!isset(self::COSTS[$type])) {
            return ['status' => 0, 'error' => 'Invalid senjutsu type'];
        }

        Log::info("AMF Senjutsu.discoverSenjutsu: Char $charId Type $type");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->level < self::REQUIRE_LEVEL) {
            return ['status' => 2, 'result' => 'Level too low'];
        }

        if ($char->senjutsu_type) {
            return ['status' => 2, 'result' => 'You already learned a Sage Mode'];
        }

        $cost = self::COSTS[$type];
        if (($cost['token'] ?? 0) > 0) {
            if ((int)$char->user->tokens < (int)$cost['token']) {
                return ['status' => 2, 'result' => 'Not enough tokens'];
            }
            $char->user->tokens -= (int)$cost['token'];
            $char->user->save();
        } else {
            if ((int)$char->gold < (int)$cost['gold']) {
                return ['status' => 2, 'result' => 'Not enough gold'];
            }
            $char->gold -= (int)$cost['gold'];
        }

        $char->senjutsu_type = $type;
        $char->save();

        return [
            'status' => 1,
            'result' => 'Senjutsu discovered!',
            'type' => $type,
        ];
    }
}
