<?php

namespace App\Services\Amf\SpecialJouninExamService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    use ValidatesSession;

    /**
     * promoteToSpecialJounin
     */
    public function promoteToSpecialJounin($sessionKey, $charId, $classSkillId = null)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF SpecialJouninExam.promoteToSpecialJounin: Char $charId Skill $classSkillId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->rank < Character::RANK_JOUNIN) {
            return ['status' => 2, 'result' => 'You must be a Jounin to take the Special Jounin Exam!'];
        }
        if ($char->level < 60) {
            return ['status' => 2, 'result' => 'You must be Level 60 to take the Special Jounin Exam!'];
        }

        $progress = array_filter(explode(',', (string)$char->special_jounin_exam_progress), 'strlen');
        $progress = array_pad($progress, 13, '0');
        $allCompleted = true;
        foreach (array_slice($progress, 0, 13) as $status) {
            if ($status !== '2') {
                $allCompleted = false;
                break;
            }
        }

        if (!$allCompleted) {
            return ['status' => 2, 'result' => 'You must complete all exam stages first!'];
        }

        // Replay path: already Special Jounin or above — reset progress, no rewards.
        if ($char->rank >= Character::RANK_SPECIAL_JOUNIN) {
            $char->special_jounin_exam_progress = '1,1,1,1,1,1,1,1,1,1,1,1,1';
            $char->save();
            Log::info("Special Jounin Exam replay completed for Char $charId (rank {$char->rank}), progress reset.");
            return ['status' => 1, 'error' => 0, 'rewards' => [], 'result' => 'Promoted'];
        }

        // Add Reward Item (Special Jounin Vest)
        $genderSuffix = $char->gender == 0 ? '_0' : '_1';
        $vestId = 'set_588' . $genderSuffix;

        $itemsToAward = [$vestId];

        foreach ($itemsToAward as $rewardId) {
            $itemConfig = Item::where('item_id', $rewardId)->first();
            if (!$itemConfig) {
                // Try without suffix
                $rewardId = substr($rewardId, 0, -2);
                $itemConfig = Item::where('item_id', $rewardId)->first();
            }

            if ($itemConfig) {
                CharacterItem::updateOrCreate(
                    ['character_id' => $charId, 'item_id' => $rewardId],
                    ['quantity' => \Illuminate\Support\Facades\DB::raw('quantity + 1'), 'category' => 'set']
                );
            }
        }

        // Add Skills
        $skillsToAward = ['skill_345'];
        if ($classSkillId) $skillsToAward[] = $classSkillId;

        foreach ($skillsToAward as $skillId) {
            CharacterSkill::firstOrCreate([
                'character_id' => $charId,
                'skill_id' => $skillId
            ]);
        }

        // Promote
        $char->update([
            'rank' => 7, // Special Jounin
            'class' => $classSkillId,
            'special_jounin_claimed' => 1
        ]);

        return [
            'status' => 1,
            'error' => 0,
            'rewards' => [
                'skill_345',
                'set_588_%s'
            ],
            'result' => 'Promoted'
        ];
    }
}
