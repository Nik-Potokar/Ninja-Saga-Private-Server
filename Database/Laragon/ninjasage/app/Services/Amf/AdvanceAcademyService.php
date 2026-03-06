<?php

namespace App\Services\Amf;

use App\Services\Amf\AdvanceAcademyService\UpgradeService;

class AdvanceAcademyService
{

    private UpgradeService $upgradeService;

    public function __construct()
    {
        $this->upgradeService = new UpgradeService();
    }

    /**
     * upgradeSkill
     * Params: [charId, sessionKey, nextSkillId]
     */
    public function upgradeSkill($charId, $sessionKey, $nextSkillId)
    {
        return $this->upgradeService->upgradeSkill($charId, $sessionKey, $nextSkillId);
    }
}
