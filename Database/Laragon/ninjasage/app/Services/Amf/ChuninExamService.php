<?php

namespace App\Services\Amf;

use App\Services\Amf\ChuninExamService\DataService;
use App\Services\Amf\ChuninExamService\PromotionService;
use App\Services\Amf\ChuninExamService\StageService;

class ChuninExamService
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
     */
    public function startStage($sessionKey, $charId, $targetStage)
    {
        return $this->stageService->startStage($sessionKey, $charId, $targetStage);
    }

    /**
     * finishStage
     * Parameters: [sessionKey, charId, stageIndex]
     */
    public function finishStage($sessionKey, $charId, $stageIndex)
    {
        return $this->stageService->finishStage($sessionKey, $charId, $stageIndex);
    }

    /**
     * promoteToChunin
     * Parameters: [sessionKey, charId]
     */
    public function promoteToChunin($sessionKey, $charId)
    {
        return $this->promotionService->promoteToChunin($sessionKey, $charId);
    }

    /**
     * Helper to complete a stage (to be called from BattleSystem if needed)
     */
    public function completeStage($charId, $stageIndex)
    {
        return $this->stageService->completeStage($charId, $stageIndex);
    }
}
