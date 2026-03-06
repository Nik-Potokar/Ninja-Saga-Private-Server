<?php

namespace App\Services\Amf\PvPService;

use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class AccessService
{
    use ValidatesSession;

    public function checkAccess($charId, $sessionKey): array
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PvPService.checkAccess: Char $charId");

        $url = config('app.url') . '/pvp/pvp.swf';

        return [
            'status' => 1,
            'error' => 0,
            'url' => $url,
        ];
    }
}
