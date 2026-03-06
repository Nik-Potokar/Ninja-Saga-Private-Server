<?php

namespace App\Services\Amf;

use App\Services\Amf\PvPService\AccessService;
use App\Services\Amf\PvPService\BattleHistoryService;
use App\Services\Amf\PvPService\BugReportService;
use App\Services\Amf\PvPService\LeaderboardService;
use App\Services\Amf\PvPService\StatsService;

class PvPService
{
    private AccessService $accessService;
    private StatsService $statsService;
    private LeaderboardService $leaderboardService;
    private BattleHistoryService $battleHistoryService;
    private BugReportService $bugReportService;

    public function __construct()
    {
        $this->accessService = new AccessService();
        $this->statsService = new StatsService();
        $this->leaderboardService = new LeaderboardService();
        $this->battleHistoryService = new BattleHistoryService();
        $this->bugReportService = new BugReportService();
    }

    public function checkAccess($charId, $sessionKey)
    {
        return $this->accessService->checkAccess($charId, $sessionKey);
    }

    public function getCharacterStats($charId, $sessionKey)
    {
        return $this->statsService->getCharacterStats($charId, $sessionKey);
    }

    public function getLeaderboard($charId, $sessionKey)
    {
        return $this->leaderboardService->getLeaderboard($charId, $sessionKey);
    }

    public function getBattleActivity($charId, $sessionKey)
    {
        return $this->battleHistoryService->getBattleActivity($charId, $sessionKey);
    }

    public function getDetailBattle($charId, $sessionKey, $battleId)
    {
        return $this->battleHistoryService->getDetailBattle($charId, $sessionKey, $battleId);
    }

    public function reportBug($charId, $sessionKey, $title, $desc)
    {
        return $this->bugReportService->reportBug($charId, $sessionKey, $title, $desc);
    }
}
