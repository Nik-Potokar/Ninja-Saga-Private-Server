<?php

namespace App\Services\Amf\NinjaTutorExamService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class DataService
{
    use ValidatesSession;

    /**
     * getData
     */
    public function getData($sessionKey, $charId)
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

        Log::info("AMF NinjaTutorExam.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $progress = $this->normalizeProgress($char);

        $data = [];
        foreach ($progress as $index => $status) {
            $data[] = [
                'id' => $this->chapterId($index),
                'status' => (int)$status,
                'claimed' => $index === 0 ? (bool)$char->ninja_tutor_claimed : false
            ];
        }

        return [
            'status' => 1,
            'error' => 0,
            'data' => $data
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

        $normalized = implode(',', $progress);
        if ($char->ninja_tutor_exam_progress !== $normalized) {
            $char->ninja_tutor_exam_progress = $normalized;
            $char->save();
        }

        return $progress;
    }

    private function chapterId(int $index): int
    {
        return 24 + $index;
    }
}
