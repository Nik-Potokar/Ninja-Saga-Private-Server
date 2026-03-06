<?php

namespace App\Services\Amf\SenjutsuService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class UpgradeService
{
    use ValidatesSession;

    /**
     * upgradeSkill
     * Params: [charId, sessionKey, baseSkillId, isMax]
     */
    public function upgradeSkill($charId, $sessionKey, $baseSkillId, $isMax = false)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Senjutsu.upgradeSkill: Char $charId skill $baseSkillId isMax $isMax");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // 1. Determine current level
        // Senjutsu skills are usually stored in 'senjutsu_skills' field as 'id:lv,id:lv'
        $ownedSenjutsu = $this->parseSkills($char->senjutsu_skills);
        $currentLevel = $ownedSenjutsu[$baseSkillId] ?? 0;

        if ($currentLevel >= 10) {
            return ['status' => 2, 'result' => 'Already at maximum level!'];
        }

        $nextLevel = $currentLevel + 1;
        $totalSpentSs = 0;

        if ($isMax) {
            while ($nextLevel <= 10) {
                $cost = $this->getSsCost($nextLevel);
                if ($char->ss >= $totalSpentSs + $cost) {
                    $totalSpentSs += $cost;
                    $nextLevel++;
                } else {
                    break;
                }
            }
            $nextLevel--;
            if ($nextLevel <= $currentLevel) {
                return ['status' => 2, 'result' => 'Not enough SS to upgrade!'];
            }
        } else {
            $totalSpentSs = $this->getSsCost($nextLevel);
            if ($char->ss < $totalSpentSs) {
                return ['status' => 2, 'result' => 'Not enough SS!'];
            }
        }

        // 2. Execute Upgrade
        $char->ss -= $totalSpentSs;
        $ownedSenjutsu[$baseSkillId] = $nextLevel;
        $char->senjutsu_skills = $this->formatSkills($ownedSenjutsu);
        $char->save();

        return [
            'status' => 1,
            'spent_ss' => $totalSpentSs,
            'result' => 'Senjutsu upgraded successfully!'
        ];
    }

    private function getSsCost($level)
    {
        $costs = [
            1 => 5, 2 => 10, 3 => 25, 4 => 50, 5 => 100,
            6 => 200, 7 => 300, 8 => 450, 9 => 600, 10 => 800
        ];
        return $costs[$level] ?? 999999;
    }

    private function parseSkills($skillStr)
    {
        if (!$skillStr) return [];
        $skills = [];
        $parts = explode(',', $skillStr);
        foreach ($parts as $p) {
            if (strpos($p, ':') !== false) {
                list($id, $lv) = explode(':', $p);
                $skills[$id] = (int)$lv;
            } else {
                $skills[$p] = 1;
            }
        }
        return $skills;
    }

    private function formatSkills($skills)
    {
        $parts = [];
        foreach ($skills as $id => $lv) {
            $parts[] = "$id:$lv";
        }
        return implode(',', $parts);
    }
}
