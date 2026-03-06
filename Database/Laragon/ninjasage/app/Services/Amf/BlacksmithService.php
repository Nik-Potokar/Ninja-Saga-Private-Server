<?php

namespace App\Services\Amf;

use App\Services\Amf\BlacksmithService\ForgeService;

class BlacksmithService
{
    private ForgeService $forgeService;

    public function __construct()
    {
        $this->forgeService = new ForgeService();
    }

    /**
     * forgeItem
     * Params: [charId, sessionKey, weaponId, currency]
     */
    public function forgeItem($charId, $sessionKey, $weaponId, $currency)
    {
        return $this->forgeService->forgeItem($charId, $sessionKey, $weaponId, $currency);
    }
}
