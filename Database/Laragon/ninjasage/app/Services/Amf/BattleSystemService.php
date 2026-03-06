<?php

namespace App\Services\Amf;

use App\Services\Amf\BattleSystemService\FinishService;
use App\Services\Amf\BattleSystemService\StartService;
use App\Services\Amf\BattleSystemService\DataService;

class BattleSystemService
{
    private StartService $startService;
    private FinishService $finishService;
    private DataService $dataService;

    public function __construct()
    {
        $this->startService = new StartService();
        $this->finishService = new FinishService();
        $this->dataService = new DataService();
    }

    /**
     * startMission
     */
    public function startMission($charId, $missionId, $enemyId, $enemyStats, $unknown, $hash, $sessionKey, $stage = null)
    {
        return $this->startService->startMission($charId, $missionId, $enemyId, $enemyStats, $unknown, $hash, $sessionKey);
    }

    /**
     * finishMission
     */
    public function finishMission($charId, $missionId, $token, $hash, $score, $sessionKey, $battleData, $unknown)
    {
        return $this->finishService->finishMission($charId, $missionId, $token, $hash, $score, $sessionKey, $battleData, $unknown);
    }

    /**
     * getMissionSData
     */
    public function getMissionSData($charId, $sessionKey)
    {
        return $this->dataService->getMissionSData($charId, $sessionKey);
    }
}
