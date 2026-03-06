<?php

namespace App\Services\Amf\NinjaTutorExamService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    use ValidatesSession;

    /**
     * promoteToNinjaTutor
     */
    public function promoteToNinjaTutor($sessionKey, $charId)
    {
        if (is_numeric($sessionKey) && !is_numeric($charId)) {
            $temp = $sessionKey;
            $sessionKey = $charId;
            $charId = $temp;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF NinjaTutorExam.promoteToNinjaTutor: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->rank < Character::RANK_SPECIAL_JOUNIN) {
            return ['status' => 2, 'result' => 'You must be a Special Jounin to take the Ninja Tutor Exam!'];
        }
        if ($char->level < 80) {
            return ['status' => 2, 'result' => 'You must be Level 80 to take the Ninja Tutor Exam!'];
        }

        $progress = array_filter(explode(',', (string)$char->ninja_tutor_exam_progress), 'strlen');
        $progress = array_pad($progress, 12, '0');
        $allCompleted = true;
        foreach (array_slice($progress, 0, 12) as $status) {
            if ($status !== '2') {
                $allCompleted = false;
                break;
            }
        }

        if (!$allCompleted) {
            return ['status' => 2, 'result' => 'You must complete all exam stages first!'];
        }

        // Replay path: already Senior Ninja Tutor (rank 9) or above — reset progress, no rewards.
        if ($char->rank >= 9) {
            $char->ninja_tutor_exam_progress = '1,1,1,1,1,1,1,1,1,1,1,1';
            $char->save();
            Log::info("Ninja Tutor Exam replay completed for Char $charId (rank {$char->rank}), progress reset.");
            return ['status' => 1, 'error' => 0, 'rewards' => [], 'result' => 'Congratulations! You are now a Senior Ninja Tutor!'];
        }

        // Add Rewards
        $genderSuffix = $char->gender == 0 ? '_0' : '_1';
        $itemsToAward = [
            'wpn_988' => 'weapon',
            'set_942' . $genderSuffix => 'set',
            'back_430' => 'back'
        ];

        foreach ($itemsToAward as $itemId => $cat) {
            $itemConfig = Item::where('item_id', $itemId)->first();
            if (!$itemConfig) {
                // Try without suffix if it has one
                if (str_contains($itemId, '_')) {
                    $itemIdNoSuffix = explode('_', $itemId);
                    if (count($itemIdNoSuffix) > 1) {
                        $itemConfig = Item::where('item_id', $itemIdNoSuffix[0] . '_' . $itemIdNoSuffix[1])->first();
                    }
                }
            }

            CharacterItem::updateOrCreate(
                ['character_id' => $charId, 'item_id' => $itemId],
                ['quantity' => \Illuminate\Support\Facades\DB::raw('quantity + 1'), 'category' => $cat]
            );
        }

        // Promote to Senior Ninja Tutor (Rank 9)
        $char->update([
            'rank' => 9,
            'ninja_tutor_claimed' => 1
        ]);

        return [
            'status' => 1,
            'error' => 0,
            'rewards' => [
                'wpn_988',
                'set_942_%s',
                'back_430'
            ],
            'result' => 'Congratulations! You are now a Senior Ninja Tutor!'
        ];
    }
}
