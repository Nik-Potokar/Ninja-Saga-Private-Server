<?php

namespace App\Services\Amf;

use App\Services\Amf\JouninExamService\DataService;
use App\Services\Amf\JouninExamService\PromotionService;
use App\Services\Amf\JouninExamService\StageService;

class JouninExamService
{
    private DataService $dataService;
    private StageService $stageService;
    private PromotionService $promotionService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->stageService = new StageService();
        $this->promotionService = new PromotionService();
    }

    /**
     * getData
     * Parameters: [sessionKey, charId]
     */
    public function getData($sessionKey, $charId)
    {
        return $this->dataService->getData($sessionKey, $charId);
    }

    /**
     * startStage
     * Parameters: [sessionKey, charId, targetStage]
     * targetStage from client is 6-10 for stages 1-5
     */
    public function startStage($sessionKey, $charId, $targetStage)
    {
        return $this->stageService->startStage($sessionKey, $charId, $targetStage);
    }

    /**
     * finishStage
     * Parameters: [sessionKey, charId, stageId, ...args]
     */
    public function finishStage($sessionKey, $charId, $stageId, ...$args)
    {
        return $this->stageService->finishStage($sessionKey, $charId, $stageId, ...$args);
    }

    /**
     * promoteToJounin
     * Parameters: [sessionKey, charId]
     */
    public function promoteToJounin($sessionKey, $charId)
    {
        return $this->promotionService->promoteToJounin($sessionKey, $charId);
    }
}
