<?php

namespace App\Services\Amf;

use App\Services\Amf\DailyRewardService\AttendanceService;
use App\Services\Amf\DailyRewardService\ScrollService;
use App\Services\Amf\DailyRewardService\StatusService;
use App\Services\Amf\DailyRewardService\TokenService;
use App\Services\Amf\DailyRewardService\XpService;

class DailyRewardService
{
    private StatusService $statusService;
    private TokenService $tokenService;
    private XpService $xpService;
    private AttendanceService $attendanceService;
    private ScrollService $scrollService;

    public function __construct()
    {
        $this->statusService = new StatusService();
        $this->tokenService = new TokenService();
        $this->xpService = new XpService();
        $this->attendanceService = new AttendanceService();
        $this->scrollService = new ScrollService();
    }

    /**
     * getDailyData
     * Params: [charId, sessionKey]
     */
    public function getDailyData($charId, $sessionKey)
    {
        return $this->statusService->getDailyData($charId, $sessionKey);
    }

    /**
     * getDailyTokenData
     * Params: [charId, sessionKey]
     */
    public function getDailyTokenData($charId, $sessionKey)
    {
        return $this->tokenService->getDailyTokenData($charId, $sessionKey);
    }

    /**
     * claimDailyXP
     * Params: [charId, sessionKey]
     */
    public function claimDailyXP($charId, $sessionKey)
    {
        return $this->xpService->claimDailyXP($charId, $sessionKey);
    }

    /**
     * claimDoubleXP
     * Params: [charId, sessionKey]
     */
    public function claimDoubleXP($charId, $sessionKey)
    {
        return $this->xpService->claimDoubleXP($charId, $sessionKey);
    }

    /**
     * getAttendances
     * Params: [charId, sessionKey]
     */
    public function getAttendances($charId, $sessionKey)
    {
        return $this->attendanceService->getAttendances($charId, $sessionKey);
    }

    /**
     * claimAttendanceReward
     * Params: [charId, sessionKey, rewardId]
     */
    public function claimAttendanceReward($charId, $sessionKey, $rewardId)
    {
        return $this->attendanceService->claimAttendanceReward($charId, $sessionKey, $rewardId);
    }

    /**
     * claimScrollOfWisdom
     * Params: [charId, sessionKey]
     */
    public function claimScrollOfWisdom($charId, $sessionKey)
    {
        return $this->scrollService->claimScrollOfWisdom($charId, $sessionKey);
    }
}
