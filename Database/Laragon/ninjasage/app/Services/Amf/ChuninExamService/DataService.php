<?php

namespace App\Services\Amf\ChuninExamService;

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

        Log::info("AMF ChuninExam.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $progress = explode(',', $char->chunin_exam_progress);

        $data = [];
        foreach ($progress as $index => $status) {
            $data[] = [
                'status' => (string)$status,
                'claimed' => ($index === 0) ? ($char->chunin_claimed ? "1" : "0") : "0"
            ];
        }

        return [
            'status' => 1,
            'data' => $data
        ];
    }
}
