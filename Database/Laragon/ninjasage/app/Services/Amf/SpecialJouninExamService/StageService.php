<?php

namespace App\Services\Amf\SpecialJouninExamService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class StageService
{
    use ValidatesSession;

    /**
     * startStage
     */
    public function startStage($sessionKey, $charId, $targetStage)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF SpecialJouninExam.startStage: Char $charId Stage $targetStage");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $guard = $this->guardExamAccess($char);
        if ($guard) {
            return $guard;
        }

        $progress = $this->normalizeProgress($char);
        $stageIndex = (int)$targetStage - 11;
        if ($stageIndex < 0 || $stageIndex >= 13) {
            return ['status' => 0, 'error' => 'Invalid stage'];
        }

        if ($progress[$stageIndex] === '0') {
            return ['status' => 2, 'result' => 'Stage is locked!'];
        }

        return [
            'status' => 1,
            'error' => 0,
            'data' => $this->buildProgressData($char, $progress),
            'result' => 'start_stage_' . ((int)$targetStage - 5),
            'hash' => $this->makeHash()
        ];
    }

    /**
     * finishStage
     */
    public function finishStage($sessionKey, $charId, $stageId, ...$args)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF SpecialJouninExam.finishStage: Char $charId Stage $stageId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $progress = $this->normalizeProgress($char);
        $stageIndex = (int)$stageId - 11;
        if ($stageIndex < 0 || $stageIndex >= 13) {
            return ['status' => 0, 'error' => 'Invalid stage'];
        }

        $progress[$stageIndex] = '2';
        if (isset($progress[$stageIndex + 1]) && $progress[$stageIndex + 1] === '0') {
            $progress[$stageIndex + 1] = '1';
        }

        $char->special_jounin_exam_progress = implode(',', $progress);

        // Auto-promote if all stages are completed
        $allCompleted = true;
        for ($i = 0; $i < 13; $i++) {
            if (!isset($progress[$i]) || $progress[$i] !== "2") {
                $allCompleted = false;
                break;
            }
        }

        if ($allCompleted && $char->rank < 7) {
            // Note: Client handles promotion via promoteToSpecialJounin explicitly after animation
            // But we can log it here.
            Log::info("Special Jounin Exam All Stages Completed for Char $charId");
        }

        $char->save();

        return [
            'status' => 1,
            'error' => 0,
            'data' => $this->buildProgressData($char, $progress),
            'result' => 'Completed',
            'hash' => $this->makeHash()
        ];
    }

    private function normalizeProgress(Character $char): array
    {
        $progress = array_filter(explode(',', (string)$char->special_jounin_exam_progress), 'strlen');

        if (count($progress) === 0) {
            $progress = ['1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'];
        }

        if (count($progress) < 13) {
            $progress = array_pad($progress, 13, '0');
        } elseif (count($progress) > 13) {
            $progress = array_slice($progress, 0, 13);
        }

        return $progress;
    }

    private function buildProgressData(Character $char, array $progress): array
    {
        $data = [];
        foreach ($progress as $index => $status) {
            $data[] = [
                'status' => (int)$status,
                'claimed' => $index === 0 ? (bool)$char->special_jounin_claimed : false
            ];
        }

        return $data;
    }

    private function makeHash(): string
    {
        return hash('sha256', random_bytes(32));
    }

    private function guardExamAccess(Character $char): ?array
    {
        if ($char->rank < Character::RANK_JOUNIN) {
            return ['status' => 2, 'result' => 'You must be a Jounin to take the Special Jounin Exam!'];
        }
        if ($char->level < 60) {
            return ['status' => 2, 'result' => 'You must be Level 60 to take the Special Jounin Exam!'];
        }

        return null;
    }
}
