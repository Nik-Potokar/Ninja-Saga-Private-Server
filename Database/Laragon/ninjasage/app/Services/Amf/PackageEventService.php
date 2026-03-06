<?php

namespace App\Services\Amf;

use App\Services\Amf\PackageEventService\ExecuteService;
use App\Services\Amf\PackageEventService\PurchaseService;

class PackageEventService
{
    private ExecuteService $executeService;
    private PurchaseService $purchaseService;

    public function __construct()
    {
        $this->executeService = new ExecuteService();
        $this->purchaseService = new PurchaseService();
    }

    /**
     * executeService
     * Dispatcher for pet-related actions.
     */
    public function executeService($subService, $params)
    {
        return $this->executeService->executeService($subService, $params);
    }

    /**
     * buyChuninPackage
     */
    public function buyChuninPackage($charIdOrParams, $sessionKey = null)
    {
        return $this->purchaseService->buyChuninPackage($charIdOrParams, $sessionKey);
    }

    /**
     * buyDesignContest
     * Params: [charId, sessionKey, itemId]
     */
    public function buyDesignContest($charId, $sessionKey, $itemId)
    {
        return $this->purchaseService->buyDesignContest($charId, $sessionKey, $itemId);
    }
}
