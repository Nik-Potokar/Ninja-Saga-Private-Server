<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class RegistrationService
{
    use ValidatesSession;

    /**
     * characterRegister
     */
    public function characterRegister($params)
    {
        $accountId = $params[0];
        $sessionKeyHash = $params[1];
        $charName = $params[2];
        $gender = $params[3];
        $element = $params[4];
        $hairColor = $params[5];
        $hairNum = $params[6];

        Log::info("AMF Character Register: $charName for User $accountId Element $element");

        // $guard = $this->guardUserSession((int)$accountId, $sessionKeyHash);
        // if ($guard) {
        //     return $guard;
        // }

        if (strlen($charName) < 2) {
            return ['status' => 2, 'error' => 'Character name too short.'];
        }

        if (Character::where('name', $charName)->exists()) {
            return ['status' => 2, 'error' => 'Character name already taken.'];
        }

        $user = User::find($accountId);
        if (!$user) return ['status' => 0, 'error' => 'User not found.'];

        $characterCount = Character::where('user_id', $accountId)->count();
        $maxCharacters = ($user->account_type == 1) ? 6 : 1;

        if ($characterCount >= $maxCharacters) {
            return [
                'status' => 2,
                'result' => ($user->account_type == 1)
                    ? 'You have reached the maximum limit of 6 characters.'
                    : 'Free users can only create 1 character. Upgrade to Premium for 6 slots!'
            ];
        }

        try {
            $elementSkillMap = [
                1 => 'skill_13', 2 => 'skill_10', 3 => 'skill_01', 4 => 'skill_12', 5 => 'skill_09',
            ];

            $basicSkill = $elementSkillMap[$element] ?? 'skill_01';
            $genderSuffix = $gender == 0 ? '_0' : '_1';
            $defaultSkills = [$basicSkill]; // Give character basic skill only one

            $character = Character::create([
                'user_id' => $accountId,
                'name' => $charName,
                'gender' => $gender,
                'element_1' => $element,
                'hair_style' => 'hair_' . str_pad($hairNum, 2, '0', STR_PAD_LEFT) . $genderSuffix,
                'hair_color' => $hairColor,
                'point_free' => 1,
                'gold' => 1000,
                'equipment_weapon' => 'wpn_01',
                'equipment_back' => 'back_01',
                'equipment_clothing' => 'set_01' . $genderSuffix,
                'equipment_accessory' => 'accessory_01',
                'equipment_skills' => implode(',', $defaultSkills),
            ]);

            foreach ($defaultSkills as $s) {
                CharacterSkill::create(['character_id' => $character->id, 'skill_id' => $s]);
            }

            $defaults = [
                ['id' => 'wpn_01', 'cat' => 'weapon'],
                ['id' => 'back_01', 'cat' => 'back'],
                ['id' => 'accessory_01', 'cat' => 'accessory'],
                ['id' => 'set_01' . $genderSuffix, 'cat' => 'set'],
            ];

            foreach ($defaults as $d) {
                CharacterItem::create([
                    'character_id' => $character->id,
                    'item_id' => $d['id'],
                    'quantity' => 1,
                    'category' => $d['cat']
                ]);
            }

            // $user->tokens += 10000; // Removed to prevent reset/exploit
            // $user->save();

            return ['status' => 1];

        } catch (\Exception $e) {
            Log::error("Character Creation Error: " . $e->getMessage());
            return [
                'status' => 0,
                'error' => 'Internal Server Error'
            ];
        }
    }
}
