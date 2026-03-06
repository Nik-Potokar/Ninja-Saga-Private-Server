<?php

namespace App\Services\Amf\SystemLoginService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class AccountService
{
    use ValidatesSession;

    /**
     * getAllCharacters
     */
    public function getAllCharacters($uid, $sessionkey)
    {
        $guard = $this->guardUserSession((int)$uid, $sessionkey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Get Characters: User $uid");
        $user = User::find($uid);
        $accountType = $user ? $user->account_type : 0;
        $tokens = $user ? $user->tokens : 0;

        $characters = Character::where('user_id', $uid)->get();
        $accountData = [];
        foreach ($characters as $char) {
            $accountData[] = [
                'char_id' => $char->id,
                'acc_id' => $uid,
                'character_name' => $char->name,
                'character_level' => $char->level,
                'character_xp' => $char->xp,
                'character_gender' => $char->gender,
                'character_rank' => (int)$char->rank,
                'character_prestige' => $char->prestige,
                'character_element_1' => $char->element_1,
                'character_element_2' => $char->element_2 ?: null,
                'character_element_3' => $char->element_3 ?: null,
                'character_talent_1' => $char->talent_1 ?: null,
                'character_talent_2' => $char->talent_2 ?: null,
                'character_talent_3' => $char->talent_3 ?: null,
                'character_gold' => $char->gold,
                'character_tp' => $char->tp,
            ];
        }
        return [
            'status' => 1,
            'error' => 0,
            'account_type' => $accountType,
            'emblem_duration' => -1,
            'tokens' => $tokens, // Root tokens from account
            'total_characters' => count($accountData),
            'account_data' => $accountData
        ];
    }
}
