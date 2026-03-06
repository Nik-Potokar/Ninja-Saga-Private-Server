<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\Skill;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevToolsService
{
    use ValidatesSession;

    /**
     * addItems (Developer Tools)
     */
    public function addItems($charIdOrParams, $sessionKey = null, $itemString = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $itemString = $charIdOrParams[2] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DevTools addItems: Char $charId Items $itemString");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Security Check
        if (($char->user->account_type ?? 0) !== User::TYPE_ADMIN) {
            return ['status' => 0, 'error' => 'Permission denied.'];
        }

        $parts = explode(',', $itemString);
        foreach ($parts as $part) {
            $subParts = explode(':', trim($part));
            $itemId = $subParts[0];
            $qty = isset($subParts[1]) ? intval($subParts[1]) : 1;

            // Simple determine category
            $cat = 'item';
            if (str_starts_with($itemId, 'wpn_')) $cat = 'weapon';
            elseif (str_starts_with($itemId, 'back_')) $cat = 'back_item';
            elseif (str_starts_with($itemId, 'set_')) $cat = 'set';
            elseif (str_starts_with($itemId, 'hair_')) $cat = 'hair';
            elseif (str_starts_with($itemId, 'accessory_')) $cat = 'accessory';
            elseif (str_starts_with($itemId, 'essential_')) $cat = 'essential';

            $item = CharacterItem::where('character_id', $charId)->where('item_id', $itemId)->first();
            if ($item) {
                $item->quantity += $qty;
                $item->save();
            } else {
                CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $itemId,
                    'quantity' => $qty,
                    'category' => $cat
                ]);
            }
        }

        return ['status' => 1, 'result' => 'Items added successfully!', 'items' => $itemString];
    }

    /**
     * setCharInfo (Developer Tools)
     */
    public function setCharInfo($charIdOrParams, $sessionKey = null, $level = null, $rank = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $level = $charIdOrParams[2] ?? null;
            $rank = $charIdOrParams[3] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DevTools setCharInfo: Char $charId Lvl $level Rank $rank");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Security Check
        if (($char->user->account_type ?? 0) !== User::TYPE_ADMIN) {
            return ['status' => 0, 'error' => 'Permission denied.'];
        }

        $char->level = intval($level);

        // Rank mapping (DevTools 1-4, or raw value)
        $r = intval($rank);
        $map = [1 => 1, 2 => 3, 3 => 5, 4 => 7];
        $finalRank = $map[$r] ?? $r;

        $char->rank = $finalRank;
        $char->save();

        return ['status' => 1, 'result' => 'Character updated!'];
    }

    /**
     * toggleEmblem (Developer Tools)
     */
    public function toggleEmblem($charIdOrParams, $sessionKey = null)
    {
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

        Log::info("AMF DevTools toggleEmblem: Char $charId");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Security Check
        if (($char->user->account_type ?? 0) !== User::TYPE_ADMIN) {
            return ['status' => 0, 'error' => 'Permission denied.'];
        }

        $user = $char->user;
        // Only toggle between Free (0) and Premium (1).
        // Admin (99) stays Admin but visually gets the emblem benefit in client if we returned account_type 1.
        // But the seeder/login should handle that.
        // For DevTools toggle, we assume the Admin wants to simulate Premium or Free status.
        // But we must NOT overwrite 99 in DB if we want them to stay admin.
        // However, usually dev tools toggle the Target's status.

        // If we assume this tool toggles the account_type of the character's user:
        $user->account_type = ($user->account_type == 1) ? 0 : 1;
        $user->save();

        $status = ($user->account_type == 1) ? "Premium" : "Free";
        return ['status' => 1, 'result' => "Account status: $status"];
    }

    /**
     * learnSkill (Developer Tools)
     */
    public function learnSkill($charIdOrParams, $sessionKey = null, $skillId = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $skillId = $charIdOrParams[2] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DevTools learnSkill: Char $charId Skill $skillId");

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Security Check
        if (($char->user->account_type ?? 0) !== User::TYPE_ADMIN) {
            Log::error($char->user->account_type. " is not admin");
            return ['status' => 0, 'error' => 'Permission denied.'];
        }

        try {
            return DB::transaction(function () use ($charId, $skillId) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $user = User::lockForUpdate()->find($char->user_id);
                if (!$user) return ['status' => 0, 'error' => 'User not found'];

                if (($user->account_type ?? 0) !== User::TYPE_ADMIN) {
                    return ['status' => 0, 'error' => 'Permission denied.'];
                }

                if (CharacterSkill::where('character_id', $charId)->where('skill_id', $skillId)->exists()) {
                    return ['status' => 2];
                }

                $skill = Skill::where('skill_id', $skillId)->first();
                if (!$skill) return ['status' => 0, 'error' => 'Skill data not found!'];

                if ($char->level < $skill->level) return ['status' => 5];

                if ($skill->element >= 1 && $skill->element <= 5) {
                    $myElements = array_filter([$char->element_1, $char->element_2, $char->element_3]);
                    if (!in_array($skill->element, $myElements)) {
                        if (!$char->element_1) {
                            $char->element_1 = $skill->element;
                        } elseif (!$char->element_2) {
                            $char->element_2 = $skill->element;
                        } elseif (!$char->element_3) {
                            $char->element_3 = $skill->element;
                        } else {
                            return ['status' => 4];
                        }
                        $char->save();
                    }
                }

                CharacterSkill::create(['character_id' => $charId, 'skill_id' => $skillId]);

                return [
                    'status' => 1,
                    'result' => 'Skill learned.',
                    'data' => [
                        'character_element_1' => $char->element_1,
                        'character_element_2' => $char->element_2,
                        'character_element_3' => $char->element_3
                    ]
                ];
            });
        } catch (\Exception $e) {
            Log::error("DevTools learnSkill Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }
}
