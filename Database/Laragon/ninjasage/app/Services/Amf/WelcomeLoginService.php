<?php

namespace App\Services\Amf;

use App\Services\Amf\WelcomeLoginService\ClaimService;
use App\Services\Amf\WelcomeLoginService\QueryService;

class WelcomeLoginService
{
    private QueryService $queryService;
    private ClaimService $claimService;

    public function __construct()
    {
        $this->queryService = new QueryService();
        $this->claimService = new ClaimService();
    }

    /**
     * get
     * Parameters: [charId, sessionKey]
     */
    public function get($charId, $sessionKey)
    {
        return $this->queryService->get($charId, $sessionKey);
    }

    /**
     * claim
     * Parameters: [charId, sessionKey, rewardIndex]
     */
    public function claim($charId, $sessionKey, $rewardIndex)
    {
        return $this->claimService->claim($charId, $sessionKey, $rewardIndex);
    }
}
