<?php

namespace App\Services\Amf;

use App\Services\Amf\EudemonGardenService\DataService;
use App\Services\Amf\EudemonGardenService\HuntService;
use App\Services\Amf\EudemonGardenService\PurchaseService;

class EudemonGardenService
{
    private DataService $dataService;
    private PurchaseService $purchaseService;
    private HuntService $huntService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->purchaseService = new PurchaseService();
        $this->huntService = new HuntService();
    }

    /**
     * getData
     * Params: [sessionKey, charId]
     */
    public function getData($sessionKey, $charId)
    {
        return $this->dataService->getData($sessionKey, $charId);
    }

    /**
     * buyTries
     * Params: [sessionKey, charId]
     */
    public function buyTries($sessionKey, $charId)
    {
        return $this->purchaseService->buyTries($sessionKey, $charId);
    }

    /**
     * startHunting
     * Params: [charId, bossNum, sessionKey]
     */
    public function startHunting($charId, $bossNum, $sessionKey)
    {
        return $this->huntService->startHunting($charId, $bossNum, $sessionKey);
    }

    /**
     * finishHunting
     * Params: [charId, bossNum, battleCode, hash, sessionKey, unknown]
     */
    public function finishHunting($charId, $bossNum, $battleCode, $hash, $sessionKey, $unknown)
    {
        return $this->huntService->finishHunting($charId, $bossNum, $battleCode, $hash, $sessionKey, $unknown);
    }
}
