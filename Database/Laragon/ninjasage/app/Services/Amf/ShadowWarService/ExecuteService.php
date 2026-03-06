<?php

namespace App\Services\Amf\ShadowWarService;

use Illuminate\Support\Facades\Log;
use App\Models\Character;

class ExecuteService
{
    private DataService $dataService;
    private PresetService $presetService;
    private BattleService $battleService;
    private LeaderboardService $leaderboardService;
    private ProfileService $profileService;

    public function __construct()
    {
        $this->dataService = new DataService();
        $this->presetService = new PresetService();
        $this->battleService = new BattleService();
        $this->leaderboardService = new LeaderboardService();
        $this->profileService = new ProfileService();
    }

    /**
     * executeService
     */
    public function executeService($subService, $params)
    {
        Log::info("AMF ShadowWar.executeService: SubService $subService");

        $guarded = [
            'getSeason',
            'getStatus',
            'getPresets',
            'savePreset',
            'usePreset',
            'getEnemies',
            'refreshEnemies',
            'refillEnergy',
            'startBattle',
            'finishBattle',
            'getEnemyInfo',
            'globalLeaderboard',
            'squadLeaderboard',
            'getProfile',
        ];

        if (in_array($subService, $guarded, true)) {
            $accessError = $this->checkAccess($params);
            if ($accessError) {
                return $accessError;
            }
        }

        return match ($subService) {
            'getSeason' => $this->dataService->getSeason($params),
            'getStatus' => $this->dataService->getStatus($params),
            'getPresets' => $this->presetService->getPresets($params),
            'savePreset' => $this->presetService->savePreset($params),
            'usePreset' => $this->presetService->usePreset($params),
            'getEnemies' => $this->battleService->getEnemies($params),
            'refreshEnemies' => $this->battleService->refreshEnemies($params),
            'refillEnergy' => $this->battleService->refillEnergy($params),
            'startBattle' => $this->battleService->startBattle($params),
            'finishBattle' => $this->battleService->finishBattle($params),
            'getEnemyInfo' => $this->battleService->getEnemyInfo($params),
            'globalLeaderboard' => $this->leaderboardService->globalLeaderboard($params),
            'squadLeaderboard' => $this->leaderboardService->squadLeaderboard($params),
            'getProfile' => $this->profileService->getProfile($params),
            default => ['status' => 0, 'error' => "SubService $subService not found"],
        };
    }

    private function checkAccess($params): ?array
    {
        $charId = $params[0] ?? null;
        if (!$charId) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->rank < Character::RANK_SPECIAL_JOUNIN && $char->level < 60) {
            return [
                'status' => 2,
                'result' => 'Your character must pass the special jounin exam or higher or equal to level 60'
            ];
        }

        return null;
    }
}
