<?php

namespace App\Services\Amf\PetService;

use Illuminate\Support\Facades\Log;

class ExecuteService
{
    private QueryService $queryService;
    private PurchaseService $purchaseService;
    private EquipmentService $equipmentService;
    private ManagementService $managementService;
    private VillaService $villaService;

    public function __construct()
    {
        $this->queryService = new QueryService();
        $this->purchaseService = new PurchaseService();
        $this->equipmentService = new EquipmentService();
        $this->managementService = new ManagementService();
        $this->villaService = new VillaService();
    }

    /**
     * executeService
     * Dispatcher for pet-related actions.
     */
    public function executeService($subService, $params)
    {
        Log::info("AMF PetService.executeService: SubService $subService");

        // The client sends [subService, [args]]
        // So $params is already the [args] array.
        if (method_exists($this->queryService, $subService)) {
            return $this->queryService->$subService($params);
        }

        if (method_exists($this->purchaseService, $subService)) {
            return $this->purchaseService->$subService($params);
        }

        if (method_exists($this->equipmentService, $subService)) {
            return $this->equipmentService->$subService($params);
        }

        if (method_exists($this->managementService, $subService)) {
            return $this->managementService->$subService($params);
        }

        if (method_exists($this->villaService, $subService)) {
            return $this->villaService->$subService($params);
        }

        Log::error("PetService: SubService $subService not implemented.");
        return ['status' => 0, 'error' => "SubService $subService not found"];
    }
}