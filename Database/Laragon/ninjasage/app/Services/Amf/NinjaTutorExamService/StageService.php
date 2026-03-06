<?php

namespace App\Services\Amf\NinjaTutorExamService;

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
        if (is_numeric($sessionKey) && !is_numeric($charId)) {
            $temp = $sessionKey;
            $sessionKey = $charId;
            $charId = $temp;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF NinjaTutorExam.startStage: Char $charId Stage $targetStage");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $guard = $this->guardExamAccess($char);
        if ($guard) {
            return $guard;
        }

        $progress = $this->normalizeProgress($char);
        $stageIndex = (int)$targetStage - 24;
        if ($stageIndex < 0 || $stageIndex >= 12) {
            return ['status' => 0, 'error' => 'Invalid stage'];
        }

        if ($progress[$stageIndex] === '0') {
            return ['status' => 2, 'result' => 'Stage is locked!'];
        }

        return [
            'status' => 1,
            'error' => 0,
            'data' => $this->buildProgressData($char, $progress),
            'result' => 'start_stage_' . (int)$targetStage,
            'hash' => $this->makeHash()
        ];
    }

    /**
     * finishStage
     */
    public function finishStage($sessionKey, $charId, $stageId, ...$args)
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

        Log::info("AMF NinjaTutorExam.finishStage: Char $charId Stage $stageId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $progress = $this->normalizeProgress($char);
        $stageIndex = (int)$stageId - 24;
        if ($stageIndex < 0 || $stageIndex >= 12) {
            return ['status' => 0, 'error' => 'Invalid stage'];
        }

        $progress[$stageIndex] = '2';

        if (isset($progress[$stageIndex + 1]) && $progress[$stageIndex + 1] === '0') {
            $progress[$stageIndex + 1] = '1';
        }

        $char->ninja_tutor_exam_progress = implode(',', $progress);
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
        $progress = array_filter(explode(',', (string)$char->ninja_tutor_exam_progress), 'strlen');

        if (count($progress) === 0) {
            $progress = ['1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'];
        }

        if (count($progress) < 12) {
            $progress = array_pad($progress, 12, '0');
        } elseif (count($progress) > 12) {
            $progress = array_slice($progress, 0, 12);
        }

        return $progress;
    }

    private function buildProgressData(Character $char, array $progress): array
    {
        $data = [];
        foreach ($progress as $index => $status) {
            $data[] = [
                'id' => 24 + $index,
                'status' => (int)$status,
                'claimed' => $index === 0 ? (bool)$char->ninja_tutor_claimed : false
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
        if ($char->rank < Character::RANK_SPECIAL_JOUNIN) {
            return ['status' => 2, 'result' => 'You must be a Special Jounin to take the Ninja Tutor Exam!'];
        }
        if ($char->level < 80) {
            return ['status' => 2, 'result' => 'You must be Level 80 to take the Ninja Tutor Exam!'];
        }

        return null;
    }
}
