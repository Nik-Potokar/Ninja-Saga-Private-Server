<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterSkillSet;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class SkillSetService
{
    use ValidatesSession;

    /**
     * getSkillSets
     */
    public function getSkillSets($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF getSkillSets: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $skillsets = CharacterSkillSet::where('character_id', $charId)
            ->orderBy('preset_index')
            ->get();

        $data = [];
        foreach ($skillsets as $s) {
            $data[] = [
                'id' => $s->id,
                'skills' => $s->skills ?: ""
            ];
        }

        return [
            'status' => 1,
            'skillsets' => $data
        ];
    }

    /**
     * saveSkillSet
     */
    public function saveSkillSet($charId, $sessionKey, $presetId, $skills)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF saveSkillSet: Char $charId Preset $presetId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $preset = CharacterSkillSet::where('id', $presetId)
            ->where('character_id', $charId)
            ->first();

        if (!$preset) return ['status' => 2, 'result' => 'Preset not found!'];

        $preset->skills = $skills;
        $preset->save();

        // Also update character's current equipped skills
        $char->equipment_skills = $skills;
        $char->save();

        return $this->getSkillSets($charId, $sessionKey);
    }

    /**
     * createSkillSet
     */
    public function createSkillSet($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF createSkillSet: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        // Premium Required
        if ($user->account_type == 0) {
            return ['status' => 2, 'result' => 'Premium required to unlock Skill Sets!'];
        }

        $count = CharacterSkillSet::where('character_id', $charId)->count();
        if ($count >= 4) {
            return ['status' => 2, 'result' => 'Maximum 4 presets allowed!'];
        }

        // Cost logic: 0, 100, 200, 300
        $cost = $count * 100;

        if ($user->tokens < $cost) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        if ($cost > 0) {
            $user->tokens -= $cost;
            $user->save();
        }

        CharacterSkillSet::create([
            'character_id' => $charId,
            'preset_index' => $count + 1,
            'skills' => $char->equipment_skills
        ]);

        return $this->getSkillSets($charId, $sessionKey);
    }
}
