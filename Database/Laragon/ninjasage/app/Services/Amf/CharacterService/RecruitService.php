<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterRecruit;
use App\Models\Npc;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class RecruitService
{
    use ValidatesSession;

    /**
     * recruitTeammate
     */
    public function recruitTeammate($charIdOrParams, $sessionKey = null, $recruitId = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $recruitId = $charIdOrParams[2] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF CharacterService.recruitTeammate: Char $charId Recruit $recruitId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        // Get NPC from Database
        $npc = Npc::where('npc_id', $recruitId)->first();

        if ($npc) {
            // Check Premium
            if ($npc->premium && $user->account_type == 0) {
                return ['status' => 2, 'result' => 'You need to be premium user to recruit this NPC!'];
            }

            // Check Cost
            if ($npc->price_tokens > 0) {
                if ($user->tokens < $npc->price_tokens) {
                    return ['status' => 2, 'result' => 'Not enough tokens!'];
                }
            }

            if ($npc->price_gold > 0) {
                if ($char->gold < $npc->price_gold) {
                    return ['status' => 2, 'result' => 'Not enough gold!'];
                }
            }

            // Deduct
            if ($npc->price_tokens > 0) {
                $user->tokens -= $npc->price_tokens;
                $user->save();
            }

            if ($npc->price_gold > 0) {
                $char->gold -= $npc->price_gold;
                $char->save();
            }
        }

        // Check Max Recruits
        if (CharacterRecruit::where('character_id', $charId)->count() >= 2) {
            return ['status' => 2, 'result' => 'You cannot recruit more teammate'];
        }

        // Create Record
        CharacterRecruit::create([
            'character_id' => $charId,
            'recruit_id' => $recruitId
        ]);

        // Return all recruits
        $recruits = CharacterRecruit::where('character_id', $charId)->pluck('recruit_id')->toArray();

        // Ensure at least one
        if (empty($recruits)) $recruits = [$recruitId];

        // Client logic: _loc3_ = CUCSG.hash(param1.recruiters[0][0]);
        $firstId = (string)$recruits[0];
        $hash = hash('sha256', $firstId);

        return [
            'status' => 1,
            'recruiters' => [
                $recruits,
                $hash
            ]
        ];
    }

    /**
     * removeRecruitments
     */
    public function removeRecruitments($charIdOrParams, $sessionKey = null)
    {
        // Unpack params if array (AMF standard)
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF removeRecruitments: Char $charId");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            CharacterRecruit::where('character_id', $charId)->delete();

            return ['status' => 1, 'result' => 'Recruited squad removed.'];

        } catch (\Exception $e) {
            Log::error("Remove Recruits Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }
}
