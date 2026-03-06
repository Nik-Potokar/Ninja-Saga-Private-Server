<?php

namespace App\Services\Amf\TalentService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\CharacterTalent;
use App\Models\Talent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class ResetService
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

        Log::info("AMF TalentService.resetTalents: Char $charId Talents " . json_encode($talents));

        if (empty($talents)) return ['status' => 0, 'error' => 'No talents selected'];

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Check Cost: 5 essential_02
        $costItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', 'essential_02')->first();

        if (!$costItem || $costItem->quantity < 5) {
            return ['status' => 2, 'error' => 'Not enough Ninja Seal Gan!'];
        }

        $allTalents = Talent::whereIn('talent_id', $talents)->get();

        foreach ($talents as $tid) {
            if ($char->talent_1 === $tid) $char->talent_1 = null;
            if ($char->talent_2 === $tid) {
                $char->talent_2 = $char->talent_3;
                $char->talent_3 = null;
            } elseif ($char->talent_3 === $tid) {
                $char->talent_3 = null;
            }

            $talentDef = $allTalents->where('talent_id', $tid)->first();
            if ($talentDef) {
                $skills = $talentDef->skills ?? [];
                $skillIds = [];
                if (is_array($skills)) {
                    foreach ($skills as $s) {
                        if (isset($s['talent_skill_id'])) $skillIds[] = $s['talent_skill_id'];
                        elseif (isset($s['id'])) $skillIds[] = $s['id'];
                    }
                }

                if (!empty($skillIds)) {
                    CharacterTalent::where('character_id', $charId)->whereIn('skill_id', $skillIds)->delete();
                    CharacterSkill::where('character_id', $charId)->whereIn('skill_id', $skillIds)->delete();
                }
            }
        }

        $costItem->quantity -= 5;
        if ($costItem->quantity <= 0) $costItem->delete();
        else $costItem->save();

        $char->save();
        TalentStringService::syncTalentString($charId);

        return ['status' => 1];
    }
}
