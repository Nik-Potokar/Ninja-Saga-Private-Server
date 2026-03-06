<?php

namespace App\Services\Amf\TalentService;

use App\Models\Character;
use App\Models\Talent;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class DiscoverService
{
    use ValidatesSession;

    /**
     * discoverTalent
     */
    public function discoverTalent($charIdOrParams, $sessionKey = null, $mode = null, $talentId = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $mode = $charIdOrParams[2] ?? null;
            $talentId = $charIdOrParams[3] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Talent.discoverTalent: Char $charId Mode $mode Talent $talentId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $talent = Talent::where('talent_id', $talentId)->first();
        if (!$talent) return ['status' => 0, 'error' => 'Talent not found'];

        // Check Cost
        if ($user->tokens < $talent->price_tokens) return ['status' => 2, 'result' => 'Not enough tokens!'];
        if ($char->gold < $talent->price_gold) return ['status' => 2, 'result' => 'Not enough gold!'];

        // Check Premium
        if ($talent->is_emblem && $user->account_type == 0) return ['status' => 6, 'result' => 'Premium required!'];

        // Determine Slot
        $newt = 0;
        if ($mode === 'Extreme') {
            $newt = 1;
            $char->talent_1 = $talentId;
        } elseif ($mode === 'Secret') {
            if (!$char->talent_2) {
                $newt = 2;
                $char->talent_2 = $talentId;
            } elseif (!$char->talent_3) {
                $newt = 3;
                $char->talent_3 = $talentId;
            } else {
                return ['status' => 2, 'result' => 'All Secret slots full!'];
            }
        } else {
            return ['status' => 0, 'error' => 'Invalid mode'];
        }

        // Deduct
        if ($talent->price_tokens > 0) {
            $user->tokens -= $talent->price_tokens;
            $user->save();
        }
        if ($talent->price_gold > 0) {
            $char->gold -= $talent->price_gold;
        }
        $char->save();

        return [
            'status' => 1,
            'newt' => $newt,
            'tokens' => (int)$user->tokens,
            'golds' => (int)$char->gold
        ];
    }
}
