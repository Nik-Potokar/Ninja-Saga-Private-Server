<?php

namespace App\Services\Amf\PackageEventService;

use Illuminate\Support\Facades\Log;

class ExecuteService
{
    private PurchaseService $purchaseService;

    public function __construct()
    {
        $this->purchaseService = new PurchaseService();
    }

    /**
     * executeService
     * Dispatcher for pet-related actions.
     */
    public function executeService($subService, $params)
    {
        Log::info("AMF PackageEvent.executeService: SubService $subService");

        if (method_exists($this->purchaseService, $subService)) {
            return $this->purchaseService->$subService($params);
        }

        Log::error("PackageEventService: SubService $subService not implemented.");
        return ['status' => 0, 'error' => "SubService $subService not found"];
    }
}
