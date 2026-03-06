<?php

namespace App\Services\Amf;

use App\Services\Amf\ShadowWarService\ExecuteService;

class ShadowWarService
{
    private ExecuteService $executeService;

    public function __construct()
    {
        $this->executeService = new ExecuteService();
    }

    /**
     * executeService
     */
    public function executeService($subService, $params)
    {
        return $this->executeService->executeService($subService, $params);
    }
}
