<?php

namespace App\Services\Amf\AdvanceAcademyService;

use App\Models\Character;
use App\Models\CharacterSkill;
use App\Models\Skill;
use App\Models\User;
use App\Models\GameConfig;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class UpgradeService
{
    use ValidatesSession;

    /**
     * upgradeSkill
     * Params: [charId, sessionKey, nextSkillId]
     */
    public function upgradeSkill($charId, $sessionKey, $nextSkillId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF AdvanceAcademy.upgradeSkill: Char $charId to $nextSkillId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        // 1. Get Skill Info
        $skill = Skill::where('skill_id', $nextSkillId)->first();
        if (!$skill) return ['status' => 2, 'result' => 'Skill info not found in database!'];

        // 2. Identify the current skill ID being replaced
        $chains = GameConfig::get('academy_chains', []);
        $currentSkillId = null;
        $foundChain = false;

        foreach ($chains as $element => $elementChains) {
            foreach ($elementChains as $chainName => $skillIds) {
                if (in_array($nextSkillId, $skillIds)) {
                    $foundChain = true;
                    $idx = array_search($nextSkillId, $skillIds);
                    if ($idx > 0) {
                        $currentSkillId = $skillIds[$idx - 1];
                    }
                    break 2;
                }
            }
        }

        if (!$foundChain) {
            return ['status' => 2, 'result' => 'This skill cannot be upgraded here!'];
        }

        // 3. Verify Ownership of previous version (if any)
        if ($currentSkillId) {
            if (!CharacterSkill::where('character_id', $char->id)->where('skill_id', $currentSkillId)->exists()) {
                return ['status' => 2, 'result' => 'You do not own the prerequisite skill!'];
            }
        }

        // 4. Check Level and Cost
        if ($char->level < $skill->level) {
            return ['status' => 2, 'result' => "Level {$skill->level} required!"];
        }

        if ($user->tokens < $skill->price_tokens) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        if ($char->gold < $skill->price_gold) {
            return ['status' => 2, 'result' => 'Not enough gold!'];
        }

        // 5. Execute Upgrade
        $user->tokens -= $skill->price_tokens;
        $user->save();

        $char->gold -= $skill->price_gold;

        // Remove old skill
        if ($currentSkillId) {
            CharacterSkill::where('character_id', $char->id)->where('skill_id', $currentSkillId)->delete();
        }

        // Add new skill
        CharacterSkill::firstOrCreate([
            'character_id' => $char->id,
            'skill_id' => $nextSkillId
        ]);

        // 6. Update Equipment if necessary
        $equipped = explode(',', $char->equipment_skills);
        $updatedEquipped = false;
        if ($currentSkillId) {
            foreach ($equipped as $idx => $id) {
                if ($id === $currentSkillId) {
                    $equipped[$idx] = $nextSkillId;
                    $updatedEquipped = true;
                }
            }
        }
        if ($updatedEquipped) {
            $char->equipment_skills = implode(',', $equipped);
        }

        $char->save();

        // 7. Prepare response
        $equippedSkillsStr = $char->equipment_skills;

        return [
            'status' => 1,
            'result' => 'Skill upgraded successfully!',
            'account_tokens' => (int)$user->tokens,
            'character_skills' => $equippedSkillsStr,
            'character_set_skills' => $equippedSkillsStr
        ];
    }
}
