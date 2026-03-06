<?php

namespace App\Services\Amf\TalentService;

use App\Models\Character;
use App\Models\CharacterTalent;
use App\Models\GameConfig;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class QueryService
{
    use ValidatesSession;

    /**
     * getTalentSkills
     */
    public function getTalentSkills($charIdOrParams, $sessionKey = null)
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

        Log::info("AMF Talent.getTalentSkills: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found', 'data' => []];

        // Build Map from talent_description GameConfig
        $talentDesc = GameConfig::get('talent_description');
        $skillMap = [];
        if ($talentDesc && is_array($talentDesc)) {
            foreach ($talentDesc as $key => $info) {
                if (preg_match('/talent_(.*)_skill_\d+/', $key, $matches)) {
                    $type = $matches[1];
                    $skillId = $info['talent_skill_id'] ?? null;
                    if ($skillId) {
                        $skillMap[$skillId] = $type;
                    }
                }
            }
        }

        // Fetch from CharacterTalent table
        $talents = CharacterTalent::where('character_id', $charId)->get();
        $data = [];

        foreach ($talents as $t) {
            $id = $t->skill_id;
            $lv = $t->level;

            $talentType = $skillMap[$id] ?? null;

            if (!$talentType && preg_match('/talent_(.*)_skill_\d+/', $id, $matches)) {
                $talentType = $matches[1];
            }

            $data[] = [
                'item_id' => $id,
                'item_level' => $lv,
                'talent_type' => $talentType
            ];
        }

        return [
            'status' => 1,
            'data' => $data
        ];
    }
}
