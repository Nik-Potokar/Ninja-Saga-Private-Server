<?php

namespace App\Services\Amf;

use App\Services\Amf\SpecialJouninExamService\DataService;
use App\Services\Amf\SpecialJouninExamService\PromotionService;
use App\Services\Amf\SpecialJouninExamService\StageService;
use App\Services\Amf\SpecialJouninExamService\TimeService;

class SpecialJouninExamService
{
    private DataService $dataService;
    private TimeService $timeService;
    private StageService $stageService;
    private PromotionService $promotionService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->timeService = new TimeService();
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
     * extraTime
     */
    public function extraTime($sessionKey, $charId)
    {
        return $this->timeService->extraTime($sessionKey, $charId);
    }

    /**
     * startStage
     */
    public function startStage($sessionKey, $charId, $targetStage)
    {
        return $this->stageService->startStage($sessionKey, $charId, $targetStage);
    }

    /**
     * finishStage
     */
    public function finishStage($sessionKey, $charId, $stageId, ...$args)
    {
        return $this->stageService->finishStage($sessionKey, $charId, $stageId, ...$args);
    }

    /**
     * promoteToSpecialJounin
     */
    public function promoteToSpecialJounin($sessionKey, $charId, $classSkillId = null)
    {
        return $this->promotionService->promoteToSpecialJounin($sessionKey, $charId, $classSkillId);
    }
}
