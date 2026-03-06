<?php

namespace App\Services\Amf;

use App\Services\Amf\TalentService\DiscoverService;
use App\Services\Amf\TalentService\PackageService;
use App\Services\Amf\TalentService\QueryService;
use App\Services\Amf\TalentService\ResetService;
use App\Services\Amf\TalentService\UpgradeService;

class TalentService
{
    private QueryService $queryService;
    private DiscoverService $discoverService;
    private PackageService $packageService;
    private UpgradeService $upgradeService;
    private ResetService $resetService;

    public function __construct()
    {
        $this->queryService = new QueryService();
        $this->discoverService = new DiscoverService();
        $this->packageService = new PackageService();
        $this->upgradeService = new UpgradeService();
        $this->resetService = new ResetService();
    }

    /**
     * getTalentSkills
     */
    public function getTalentSkills($charIdOrParams, $sessionKey = null)
    {
        return $this->queryService->getTalentSkills($charIdOrParams, $sessionKey);
    }

    /**
     * discoverTalent
     */
    public function discoverTalent($charIdOrParams, $sessionKey = null, $mode = null, $talentId = null)
    {
        return $this->discoverService->discoverTalent($charIdOrParams, $sessionKey, $mode, $talentId);
    }

    /**
     * buyPackageTP
     */
    public function buyPackageTP($charIdOrParams, $sessionKey = null, $packageIndex = null)
    {
        return $this->packageService->buyPackageTP($charIdOrParams, $sessionKey, $packageIndex);
    }

    /**
     * upgradeSkill
     */
    public function upgradeSkill($charIdOrParams, $sessionKey = null, $baseSkillId = null, $isMax = false)
    {
        return $this->upgradeService->upgradeSkill($charIdOrParams, $sessionKey, $baseSkillId, $isMax);
    }

    /**
     * resetTalents
     */
    public function resetTalents($charIdOrParams, $sessionKey = null, $talents = [])
    {
        return $this->resetService->resetTalents($charIdOrParams, $sessionKey, $talents);
    }
}
