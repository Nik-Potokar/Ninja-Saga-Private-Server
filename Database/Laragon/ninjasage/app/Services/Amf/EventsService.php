<?php

namespace App\Services\Amf;

use App\Services\Amf\EventsService\CatalogService;
use App\Services\Amf\EventsService\ExecuteService;

class EventsService
{
    private ExecuteService $executeService;
    private CatalogService $catalogService;

    public function __construct()
    {
        $this->executeService = new ExecuteService();
        $this->catalogService = new CatalogService();
    }

    /**
     * executeService
     */
    public function executeService($subService, $params)
    {
        return $this->executeService->executeService($subService, $params);
    }

    /**
     * get
     * Params: None (or possibly sessionKey/charId but passed as null in ImageDownloadTask)
     */
    public function get($params = null)
    {
        return $this->catalogService->get($params);
    }
}
