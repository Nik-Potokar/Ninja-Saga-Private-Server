<?php

namespace App\Services\Amf;

use App\Services\Amf\DailyScratchService\DataService;
use App\Services\Amf\DailyScratchService\ScratchService;

class DailyScratchService
{
    private DataService $dataService;
    private ScratchService $scratchService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->scratchService = new ScratchService();
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
     * scratch
     * Params: [charId, sessionKey]
     */
    public function scratch($charId, $sessionKey)
    {
        return $this->scratchService->scratch($charId, $sessionKey);
    }
}
