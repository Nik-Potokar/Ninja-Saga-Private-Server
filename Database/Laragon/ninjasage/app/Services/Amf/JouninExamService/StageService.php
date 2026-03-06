<?php

namespace App\Services\Amf\JouninExamService;

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
     * targetStage from client is 6-10 for stages 1-5
     */
    public function startStage($sessionKey, $charId, $targetStage)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF JouninExam.startStage: Char $charId Stage $targetStage");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $guard = $this->guardExamAccess($char);
        if ($guard) {
            return $guard;
        }

        // Map client stage ID to array index
        // Client sends 6, 7, 8, 9, 10
        // We want indices 0, 1, 2, 3, 4
        $stageIndex = $targetStage - 6;

        $guard = $this->guardExamAccess($char);
        if ($guard) {
            return $guard;
        }

        $progress = explode(',', $char->jounin_exam_progress);

        if (!isset($progress[$stageIndex]) || $progress[$stageIndex] == '0') {
            return ['status' => 2, 'result' => 'Stage is locked!'];
        }

        // Return expected structure
        // Client expects start_stage_1 for targetStage 6, etc.
        $clientStageIndex = $stageIndex + 1; // 0 -> 1, 1 -> 2, etc.

        return [
            'status' => 1,
            'hash' => Str::random(32),
            'result' => 'start_stage_' . $clientStageIndex
        ];
    }

    /**
     * finishStage
     * Parameters: [sessionKey, charId, stageId, ...args]
     */
    public function finishStage($sessionKey, $charId, $stageId, ...$args)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF JouninExam.finishStage: Char $charId Stage $stageId Args: " . json_encode($args));

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $progress = explode(',', $char->jounin_exam_progress);

        // Map client stage ID to array index
        $index = intval($stageId) - 6;

        if (isset($progress[$index])) {
            $progress[$index] = "2"; // Completed

            // Unlock next stage if it exists and is currently locked (0)
            if (isset($progress[$index + 1]) && $progress[$index + 1] == "0") {
                $progress[$index + 1] = "1"; // Unlocked
            }

            $char->jounin_exam_progress = implode(',', $progress);

            // Auto-promote if all stages are completed
            $allCompleted = true;
            foreach ($progress as $p) {
                if ($p !== "2") {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted && $char->rank < 5) {
                $char->rank = 5;
                Log::info("Jounin Auto-Promotion for Char $charId after Stage $stageId");
            }

            $char->save();
        }

        return [
            'status' => 1,
            'error' => 0
        ];
    }

    private function guardExamAccess(Character $char): ?array
    {
        if ($char->rank < Character::RANK_CHUNIN) {
            return ['status' => 2, 'result' => 'You must be a Chunin to take the Jounin Exam!'];
        }
        if ($char->level < 40) {
            return ['status' => 2, 'result' => 'You must be Level 40 to take the Jounin Exam!'];
        }

        return null;
    }
}
