<?php

namespace App\Services\Amf\SpecialJouninExamService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class DataService
{
    use ValidatesSession;

    /**
     * getData
     * Parameters: [sessionKey, charId]
     */
    public function getData($sessionKey, $charId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF SpecialJouninExam.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $progress = $this->normalizeProgress($char);

        $data = [];
        foreach ($progress as $index => $status) {
            $data[] = [
                'status' => (int)$status,
                'claimed' => $index === 0 ? (bool)$char->special_jounin_claimed : false
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
        $progress = array_filter(explode(',', (string)$char->special_jounin_exam_progress), 'strlen');

        if (count($progress) === 0) {
            $progress = ['1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'];
        }

        if (count($progress) < 13) {
            $progress = array_pad($progress, 13, '0');
        } elseif (count($progress) > 13) {
            $progress = array_slice($progress, 0, 13);
        }

        $normalized = implode(',', $progress);
        if ($char->special_jounin_exam_progress !== $normalized) {
            $char->special_jounin_exam_progress = $normalized;
            $char->save();
        }

        return $progress;
    }
}
