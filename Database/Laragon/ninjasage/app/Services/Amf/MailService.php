<?php

namespace App\Services\Amf;

use App\Services\Amf\MailService\ExecuteService;

class MailService
{
    private ExecuteService $executeService;

    public function __construct()
    {
        $this->executeService = new ExecuteService();
    }

    /**
     * executeService
     * Params: [subService, params]
     */
    public function executeService($subService, $params)
    {
        return $this->executeService->executeService($subService, $params);
    }
}
