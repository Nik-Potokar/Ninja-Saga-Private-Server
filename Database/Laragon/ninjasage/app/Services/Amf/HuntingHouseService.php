<?php

namespace App\Services\Amf;

use App\Services\Amf\HuntingHouseService\ClaimService;
use App\Services\Amf\HuntingHouseService\DataService;
use App\Services\Amf\HuntingHouseService\ForgeService;
use App\Services\Amf\HuntingHouseService\HuntService;
use App\Services\Amf\HuntingHouseService\PurchaseService;

class HuntingHouseService
{
    private DataService $dataService;
    private ClaimService $claimService;
    private PurchaseService $purchaseService;
    private HuntService $huntService;
    private ForgeService $forgeService;

    public function __construct()
    {
        $this->dataService    = new DataService();
        $this->claimService   = new ClaimService();
        $this->purchaseService = new PurchaseService();
        $this->huntService    = new HuntService();
        $this->forgeService   = new ForgeService();
    }

    /**
     * getData
     * Params: [charId, sessionKey]
     */
    public function getData($charId, $sessionKey)
    {
        return $this->dataService->getData($charId, $sessionKey);
    }

    /**
     * dailyClaim
     */
    public function dailyClaim($charId, $sessionKey)
    {
        return $this->claimService->dailyClaim($charId, $sessionKey);
    }

    /**
     * buyMaterial
     */
    public function buyMaterial($charId, $sessionKey, $amount)
    {
        return $this->purchaseService->buyMaterial($charId, $sessionKey, $amount);
    }

    /**
     * startHunting
     */
    public function startHunting($charId, $zoneId, $sessionKey)
    {
        return $this->huntService->startHunting($charId, $zoneId, $sessionKey);
    }

    /**
     * finishHunting
     */
    public function finishHunting($charId, $zoneId, $battleCode, $hash, $sessionKey, $unknown)
    {
        return $this->huntService->finishHunting($charId, $zoneId, $battleCode, $hash, $sessionKey, $unknown);
    }

    /**
     * getItems — returns the hunting forge recipe list for HuntingMarket.as
     */
    public function getItems($charId, $sessionKey)
    {
        return $this->forgeService->getItems($charId, $sessionKey);
    }

    /**
     * forgeItem — consumes materials and grants the crafted item
     */
    public function forgeItem($charId, $sessionKey, $itemId)
    {
        return $this->forgeService->forgeItem($charId, $sessionKey, $itemId);
    }
}