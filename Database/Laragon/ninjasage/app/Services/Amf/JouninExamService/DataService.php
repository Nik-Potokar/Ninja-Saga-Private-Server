<?php

namespace App\Services\Amf\JouninExamService;

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

        Log::info("AMF JouninExam.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        // Initialize progress if empty
        if (empty($char->jounin_exam_progress)) {
            $char->jounin_exam_progress = '1,0,0,0,0';
            $char->save();
        }

        $progress = explode(',', $char->jounin_exam_progress);

        $data = [];
        foreach ($progress as $index => $status) {
            $data[] = [
                'status' => (string)$status,
                'claimed' => ($index === 0) ? ($char->jounin_claimed ? "1" : "0") : "0"
            ];
        }

        return [
            'status' => 1,
            'data' => $data
        ];
    }
}
