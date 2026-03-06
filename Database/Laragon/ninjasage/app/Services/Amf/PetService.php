<?php

namespace App\Services\Amf;

use App\Services\Amf\PetService\ExecuteService;
use App\Services\Amf\PetService\ManagementService;
use App\Services\Amf\PetService\PurchaseService;
use App\Services\Amf\PetService\QueryService;
use App\Services\Amf\PetService\EquipmentService;

class PetService
{
    private ExecuteService $executeService;
    private QueryService $queryService;
    private PurchaseService $purchaseService;
    private EquipmentService $equipmentService;
    private ManagementService $managementService;

    public function __construct()
    {
        $this->executeService = new ExecuteService();
        $this->queryService = new QueryService();
        $this->purchaseService = new PurchaseService();
        $this->equipmentService = new EquipmentService();
        $this->managementService = new ManagementService();
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
     * getPets
     */
    public function getPets($params)
    {
        return $this->queryService->getPets($params);
    }

    /**
     * buyPet
     */
    public function buyPet($params)
    {
        return $this->purchaseService->buyPet($params);
    }

    /**
     * equipPet
     */
    public function equipPet($params)
    {
        return $this->equipmentService->equipPet($params);
    }

    /**
     * unequipPet
     */
    public function unequipPet($params)
    {
        return $this->equipmentService->unequipPet($params);
    }

    /**
     * releasePet
     */
    public function releasePet($params)
    {
        return $this->managementService->releasePet($params);
    }

    /**
     * renamePet
     */
    public function renamePet($charIdOrParams, $sessionKey = null, $petId = null, $newName = null)
    {
        return $this->managementService->renamePet($charIdOrParams, $sessionKey, $petId, $newName);
    }
}
