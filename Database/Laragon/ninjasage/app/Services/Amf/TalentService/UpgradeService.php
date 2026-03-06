<?php

namespace App\Services\Amf\TalentService;

use App\Models\Character;
use App\Models\CharacterTalent;
use App\Models\GameConfig;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class UpgradeService
{
    use ValidatesSession;

    /**
     * upgradeSkill
     */
    public function upgradeSkill($charIdOrParams, $sessionKey = null, $baseSkillId = null, $isMax = false)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $baseSkillId = $charIdOrParams[2] ?? null;
            $isMax = $charIdOrParams[3] ?? false;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Talent.upgradeSkill: Char $charId skill $baseSkillId isMax $isMax");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // 1. Determine current level from CharacterTalent table
        $talentRecord = CharacterTalent::where('character_id', $charId)
            ->where('skill_id', $baseSkillId)->first();
        $currentLevel = $talentRecord ? $talentRecord->level : 0;

        if ($currentLevel >= 10) {
            return ['status' => 2, 'result' => 'Already at maximum level!'];
        }

        $nextLevel = $currentLevel + 1;
        $totalSpentTp = 0;

        if ($isMax) {
            while ($nextLevel <= 10) {
                $cost = $this->getTpCost($nextLevel);
                if ($char->tp >= $totalSpentTp + $cost) {
                    $totalSpentTp += $cost;
                    $nextLevel++;
                } else {
                    break;
                }
            }
            $nextLevel--;
            if ($nextLevel <= $currentLevel) {
                return ['status' => 2, 'result' => 'Not enough TP to upgrade even one level!'];
            }
        } else {
            $totalSpentTp = $this->getTpCost($nextLevel);
            if ($char->tp < $totalSpentTp) {
                return ['status' => 2, 'result' => 'Not enough TP!'];
            }
        }

        // 2. Execute Upgrade
        $char->tp -= $totalSpentTp;
        $char->save();

        CharacterTalent::updateOrCreate(
            ['character_id' => $charId, 'skill_id' => $baseSkillId],
            ['level' => $nextLevel]
        );

        TalentStringService::syncTalentString($charId);

        return [
            'status' => 1,
            'current_tp' => (int)$char->tp,
            'result' => 'Talent upgraded successfully!'
        ];
    }

    private function getTpCost($level)
    {
        $costs = GameConfig::get('talent_tp_cost');
        if (!$costs) {
            $costs = [
                1 => 5, 2 => 10, 3 => 25, 4 => 50, 5 => 100,
                6 => 200, 7 => 300, 8 => 450, 9 => 600, 10 => 800
            ];
        }
        return $costs[$level] ?? 999999;
    }

    // Helper for legacy if needed, though getTalentSkills now uses DB
    private function parseTalents($talentStr)
    {
        if (!$talentStr) return [];
        $talents = [];
        $parts = explode(',', $talentStr);
        foreach ($parts as $p) {
            if (strpos($p, ':') !== false) {
                list($id, $lv) = explode(':', $p);
                $talents[$id] = (int)$lv;
            } else {
                $talents[$p] = 1;
            }
        }
        return $talents;
    }

    private function formatTalents($talents)
    {
        $parts = [];
        foreach ($talents as $id => $lv) {
            $parts[] = "$id:$lv";
        }
        return implode(',', $parts);
    }
}
