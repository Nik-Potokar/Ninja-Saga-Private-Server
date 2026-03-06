<?php

namespace App\Services\Amf\ChuninExamService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StageService
{
    use ValidatesSession;

    /**
     * startStage
     * Parameters: [sessionKey, charId, targetStage]
     */
    public function startStage($sessionKey, $charId, $targetStage)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF ChuninExam.startStage: Char $charId Stage $targetStage");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $guard = $this->guardExamAccess($char);
        if ($guard) {
            return $guard;
        }

        // Return expected structure
        return [
            'status' => 1,
            'hash' => Str::random(32),
            'result' => 'start_stage_' . $targetStage
        ];
    }

    /**
     * finishStage
     * Parameters: [sessionKey, charId, stageIndex]
     */
    public function finishStage($sessionKey, $charId, $stageIndex)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF ChuninExam.finishStage: Char $charId Stage $stageIndex");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $guard = $this->guardExamAccess($char);
        if ($guard) {
            return $guard;
        }

        $progress = explode(',', $char->chunin_exam_progress);
        $index = intval($stageIndex) - 1;

        if (isset($progress[$index])) {
            $progress[$index] = "2"; // Completed

            // Unlock next stage if it exists and is currently locked (0)
            if (isset($progress[$index + 1]) && $progress[$index + 1] == "0") {
                $progress[$index + 1] = "1"; // Unlocked
            }

            $char->chunin_exam_progress = implode(',', $progress);

            // Auto-promote if all stages are completed (status "2")
            $allCompleted = true;
            foreach ($progress as $p) {
                if ($p !== "2") {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted && $char->rank < 3) {
                $char->rank = 3;
                Log::info("Chunin Auto-Promotion for Char $charId after Stage $stageIndex");
            }

            $char->save();
        }

        return [
            'status' => 1,
            'error' => 0
        ];
    }

    /**
     * Helper to complete a stage (to be called from BattleSystem if needed)
     */
    public function completeStage($charId, $stageIndex)
    {
        $char = Character::find($charId);
        $progress = explode(',', $char->chunin_exam_progress);

        if (isset($progress[$stageIndex])) {
            $progress[$stageIndex] = "2"; // Completed

            // Unlock next stage if it exists and is locked
            if (isset($progress[$stageIndex + 1]) && $progress[$stageIndex + 1] == "0") {
                $progress[$stageIndex + 1] = "1"; // Unlocked
            }

            $char->update([
                'chunin_exam_progress' => implode(',', $progress)
            ]);
        }
    }

    private function guardExamAccess(Character $char): ?array
    {
        if ($char->rank < Character::RANK_GENIN) {
            return ['status' => 2, 'result' => 'You must be a Genin to take the Chunin Exam!'];
        }
        if ($char->level < 20) {
            return ['status' => 2, 'result' => 'You must be Level 20 to take the Chunin Exam!'];
        }

        return null;
    }
}
