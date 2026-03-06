<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\CharacterTalent;
use App\Models\GameConfig;
use App\Models\Talent;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class TalentService
{
    use ValidatesSession;

    /**
     * resetTalents
     */
    public function resetTalents($charIdOrParams, $sessionKey = null, $talents = [])
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $talents = $charIdOrParams[2] ?? [];
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF CharacterService.resetTalents: Char $charId Talents " . json_encode($talents));

        if (empty($talents)) return ['status' => 0, 'error' => 'No talents selected'];

        $char = Character::with('user')->find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = $char->user;
        $requiredGan = ($user && $user->account_type >= 1) ? 1 : 5;

        // Check Cost: $requiredGan essential_02
        $costItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', 'essential_02')->first();

        if (!$costItem || $costItem->quantity < $requiredGan) {
            return ['status' => 2, 'error' => 'Not enough Ninja Blood Gan!'];
        }

        $allTalents = Talent::whereIn('talent_id', $talents)->get();
        $talentDesc = GameConfig::get('talent_description');

        foreach ($talents as $tid) {
            // Clear slot
            if ($char->talent_1 === $tid) $char->talent_1 = null;
            if ($char->talent_2 === $tid) {
                // If 2 is cleared, move 3 to 2?
                // Client Logic: if 2 cleared, 2=3, 3=null.
                $char->talent_2 = $char->talent_3;
                $char->talent_3 = null;
            } elseif ($char->talent_3 === $tid) {
                $char->talent_3 = null;
            }

            // Remove Skills
            $skillIdsToRemove = [];

            // 1. Identify base skill IDs from talent_description config
            if ($talentDesc) {
                foreach ($talentDesc as $key => $info) {
                    if (str_contains($key, "talent_{$tid}_skill_")) {
                        $baseSkillId = $info['talent_skill_id'] ?? null;
                        if ($baseSkillId) {
                            $skillIdsToRemove[] = $baseSkillId;
                        }
                    }
                }
            }

            // 2. Identify skills from Talent model if present
            $talentDef = $allTalents->where('talent_id', $tid)->first();
            if ($talentDef) {
                $skills = $talentDef->skills ?? [];
                if (is_array($skills)) {
                    foreach ($skills as $s) {
                        if (isset($s['talent_skill_id'])) $skillIdsToRemove[] = $s['talent_skill_id'];
                        elseif (isset($s['id'])) {
                            $id = explode(':', $s['id'])[0];
                            $skillIdsToRemove[] = $id;
                        }
                    }
                }
            }

            $skillIdsToRemove = array_unique($skillIdsToRemove);

            if (!empty($skillIdsToRemove)) {
                // Delete ownership (CharacterSkill)
                CharacterSkill::where('character_id', $charId)
                    ->where(function($query) use ($skillIdsToRemove) {
                        $query->whereIn('skill_id', $skillIdsToRemove);
                        foreach ($skillIdsToRemove as $sid) {
                            $query->orWhere('skill_id', 'like', $sid . ':%');
                        }
                    })->delete();

                // Delete talent progress (CharacterTalent)
                CharacterTalent::where('character_id', $charId)
                    ->whereIn('skill_id', $skillIdsToRemove)->delete();

                // Also remove from equipment_skills string
                if ($char->equipment_skills) {
                    $equipped = explode(',', $char->equipment_skills);
                    $newEquipped = array_filter($equipped, function($esid) use ($skillIdsToRemove) {
                        $base = explode(':', $esid)[0];
                        return !in_array($base, $skillIdsToRemove);
                    });
                    $char->equipment_skills = implode(',', $newEquipped);
                }
            }
        }

        // Deduct Item
        $costItem->quantity -= $requiredGan;
        if ($costItem->quantity <= 0) $costItem->delete();
        else $costItem->save();

        $char->save();

        // Sync talent_skills string
        $this->syncTalentString($charId);

        return ['status' => 1];
    }

    private function syncTalentString($charId)
    {
        $talents = CharacterTalent::where('character_id', $charId)->get();
        $parts = [];
        foreach ($talents as $t) {
            $parts[] = $t->skill_id . ":" . $t->level;
        }
        $str = implode(',', $parts);
        Character::where('id', $charId)->update(['talent_skills' => $str]);
    }
}
