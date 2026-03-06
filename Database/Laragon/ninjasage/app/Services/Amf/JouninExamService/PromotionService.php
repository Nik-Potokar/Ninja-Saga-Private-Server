<?php

namespace App\Services\Amf\JouninExamService;

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
     * promoteToJounin
     * Parameters: [sessionKey, charId]
     */
    public function promoteToJounin($sessionKey, $charId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF JouninExam.promoteToJounin: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->rank < Character::RANK_CHUNIN) {
            return ['status' => 2, 'result' => 'You must be a Chunin to take the Jounin Exam!'];
        }
        if ($char->level < 40) {
            return ['status' => 2, 'result' => 'You must be Level 40 to take the Jounin Exam!'];
        }

        // Verify all stages are completed (status 2)
        $progress = explode(',', $char->jounin_exam_progress);
        $allCompleted = true;
        foreach ($progress as $status) {
            if ($status != "2") {
                $allCompleted = false;
                break;
            }
        }

        if (!$allCompleted) {
            return ['status' => 2, 'result' => 'You must complete all exam stages first!'];
        }

        // Replay path: already Jounin or above — reset progress, no rewards.
        if ($char->rank >= Character::RANK_JOUNIN) {
            $char->jounin_exam_progress = '1,1,1,1,1';
            $char->save();
            Log::info("Jounin Exam replay completed for Char $charId (rank {$char->rank}), progress reset.");
            return ['status' => 1, 'error' => 0, 'rewards' => [], 'result' => 'Promoted'];
        }

        // Add Rewards
        $skillId = 'skill_176';
        $tokenAmount = 200;
        $materials = [
            'material_01' => 5,
            'material_02' => 5,
            'material_03' => 5,
            'material_04' => 5,
            'material_05' => 5,
        ];

        CharacterSkill::firstOrCreate([
            'character_id' => $charId,
            'skill_id' => $skillId
        ]);

        $user = $char->user;
        if ($user) {
            $user->tokens += $tokenAmount;
            $user->save();
        }

        foreach ($materials as $materialId => $qty) {
            $charItem = CharacterItem::where('character_id', $charId)->where('item_id', $materialId)->first();
            if ($charItem) {
                $charItem->quantity += $qty;
                $charItem->save();
            } else {
                CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $materialId,
                    'quantity' => $qty,
                    'category' => 'material'
                ]);
            }
        }

        // Promote
        $updated = $char->update([
            'rank' => 5, // Jounin
            'jounin_claimed' => 1
        ]);

        Log::info("Jounin Promotion Char $charId: Updated=$updated");

        return [
            'status' => 1,
            'error' => 0,
            'rewards' => [
                $skillId,
                'tokens_' . $tokenAmount,
                'material_01:5',
                'material_02:5',
                'material_03:5',
                'material_04:5',
                'material_05:5',
            ],
            'result' => 'Promoted'
        ];
    }
}
