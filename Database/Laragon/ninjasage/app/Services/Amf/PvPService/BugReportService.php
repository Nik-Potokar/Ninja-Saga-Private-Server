<?php

namespace App\Services\Amf\PvPService;

use App\Models\PvpBugReport;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class BugReportService
{
    use ValidatesSession;

    public function reportBug($charId, $sessionKey, $title, $desc): array
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PvPService.reportBug: Char $charId - $title: $desc");

        if ($title || $desc) {
            PvpBugReport::create([
                'character_id' => $charId,
                'title' => (string)$title,
                'description' => (string)$desc,
            ]);
        }

        return [
            'status' => 1,
            'result' => 'Thank you! Your report has been submitted.',
        ];
    }
}
