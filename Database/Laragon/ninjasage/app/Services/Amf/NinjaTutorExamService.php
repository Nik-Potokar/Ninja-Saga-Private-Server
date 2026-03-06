<?php

namespace App\Services\Amf;

use App\Services\Amf\NinjaTutorExamService\DataService;
use App\Services\Amf\NinjaTutorExamService\PromotionService;
use App\Services\Amf\NinjaTutorExamService\StageService;
use App\Services\Amf\NinjaTutorExamService\TimeService;

class NinjaTutorExamService
{
    private DataService $dataService;
    private StageService $stageService;
    private PromotionService $promotionService;
    private TimeService $timeService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->stageService = new StageService();
        $this->promotionService = new PromotionService();
        $this->timeService = new TimeService();
    }

    /**
     * getData
     */
    public function getData($sessionKey, $charId)
    {
        return $this->dataService->getData($sessionKey, $charId);
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
     * promoteToNinjaTutor
     */
    public function promoteToNinjaTutor($sessionKey, $charId)
    {
        return $this->promotionService->promoteToNinjaTutor($sessionKey, $charId);
    }

    /**
     * extraTime
     */
    public function extraTime($sessionKey, $charId)
    {
        return $this->timeService->extraTime($sessionKey, $charId);
    }
}
