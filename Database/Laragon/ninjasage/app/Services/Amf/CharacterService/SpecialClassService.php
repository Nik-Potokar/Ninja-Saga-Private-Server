<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class SpecialClassService
{
    use ValidatesSession;

    private const VALID_CLASS_SKILLS = ['skill_4002', 'skill_4004', 'skill_4001', 'skill_4003', 'skill_4000'];

    // Token cost for changing an already-set class
    private const PRICE_EMBLEM = 2000;
    private const PRICE_FREE   = 3000;

    /**
     * changeSpecialClass
     * Params: [charId, sessionKey, classSkillId]
     *
     * First-time selection (character_class is null): no token cost.
     * Subsequent changes: deduct 2000 tokens (Emblem) or 3000 tokens (Free).
     */
    public function changeSpecialClass($charId, $sessionKey, $classSkillId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF CharacterService.changeSpecialClass: Char $charId NewClass $classSkillId");

        if (!in_array($classSkillId, self::VALID_CLASS_SKILLS, true)) {
            return ['status' => 2, 'result' => 'Invalid class selection.'];
        }

        $char = Character::find((int)$charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found.'];
        }

        if ($char->rank < Character::RANK_SPECIAL_JOUNIN) {
            return ['status' => 2, 'result' => 'You must be a Special Jounin to change your class!'];
        }

        if ($char->level < 60) {
            return ['status' => 2, 'result' => 'You must be Level 60 or above to change your class!'];
        }

        if ($char->class === $classSkillId) {
            return ['status' => 2, 'result' => 'You already have this class selected.'];
        }

        $isFirstTime = ($char->class === null);

        if (!$isFirstTime) {
            $user = User::find($char->user_id);
            if (!$user) {
                return ['status' => 0, 'error' => 'User not found.'];
            }

            $price = ($user->account_type >= 1) ? self::PRICE_EMBLEM : self::PRICE_FREE;

            if ($user->tokens < $price) {
                return ['status' => 2, 'result' => 'Not enough tokens! You need ' . $price . ' tokens to change class.'];
            }

            $user->tokens -= $price;
            $user->save();
        }

        $char->class = $classSkillId;
        $char->save();

        return [
            'status' => 1,
            'result' => 'Class changed successfully!',
        ];
    }
}
