<?php

namespace App\Services\Amf;

use App\Services\Amf\DailyRouletteService\DataService;
use App\Services\Amf\DailyRouletteService\SpinService;

class DailyRouletteService
{
    private DataService $dataService;
    private SpinService $spinService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->spinService = new SpinService();
    }

    /**
     * getData
     */
    public function getData($charId, $sessionKey)
    {
        return $this->dataService->getData($charId, $sessionKey);
    }

    /**
     * spin
     */
    public function spin($charId, $sessionKey)
    {
        return $this->spinService->spin($charId, $sessionKey);
    }
}
