<?php

namespace App\Services\Amf;

use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

/**
 * ClanService — stub that mirrors CrewService.
 *
 * Some client builds call the clan system as "ClanService.xxx" rather
 * than "CrewService.xxx". This class delegates everything to CrewService
 * so both targets return consistent, non-crashing responses.
 */
class ClanService
{
    use ValidatesSession;

    private CrewService $crew;

    public function __construct()
    {
        $this->crew = new CrewService();
    }

    public function login($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("ClanService.login → CrewService.login");
        return $this->crew->login($charId, $sessionKey, ...$args);
    }

    public function loginToClanServer($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("ClanService.loginToClanServer");
        return $this->crew->loginToClanServer($charId, $sessionKey, ...$args);
    }

    public function init($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->init($charId, $sessionKey, ...$args);
    }

    public function initialize($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->initialize($charId, $sessionKey, ...$args);
    }

    public function connect($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->connect($charId, $sessionKey, ...$args);
    }

    public function getCrewData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getCrewData($charId, $sessionKey, ...$args);
    }

    public function getClanData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getClanData($charId, $sessionKey, ...$args);
    }

    public function getData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getData($charId, $sessionKey, ...$args);
    }

    public function getInfo($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getInfo($charId, $sessionKey, ...$args);
    }

    public function getStatus($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getStatus($charId, $sessionKey, ...$args);
    }

    public function getAllClans($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getAllClans($charId, $sessionKey, ...$args);
    }

    public function getClanList($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getClanList($charId, $sessionKey, ...$args);
    }

    public function getCrewList($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getCrewList($charId, $sessionKey, ...$args);
    }

    public function searchClan($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->searchClan($charId, $sessionKey, ...$args);
    }

    public function createClan($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->createClan($charId, $sessionKey, ...$args);
    }

    public function createCrew($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->createCrew($charId, $sessionKey, ...$args);
    }

    public function joinClan($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->joinClan($charId, $sessionKey, ...$args);
    }

    public function leaveClan($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->leaveClan($charId, $sessionKey, ...$args);
    }

    public function disbandClan($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->disbandClan($charId, $sessionKey, ...$args);
    }

    public function getMembers($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getMembers($charId, $sessionKey, ...$args);
    }

    public function getClanMembers($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getClanMembers($charId, $sessionKey, ...$args);
    }

    public function kickMember($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->kickMember($charId, $sessionKey, ...$args);
    }

    public function getLeaderboard($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getLeaderboard($charId, $sessionKey, ...$args);
    }

    public function getCrewBattleData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getCrewBattleData($charId, $sessionKey, ...$args);
    }

    public function getClanBattleData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getClanBattleData($charId, $sessionKey, ...$args);
    }

    public function startBattle($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->startBattle($charId, $sessionKey, ...$args);
    }

    public function finishBattle($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->finishBattle($charId, $sessionKey, ...$args);
    }

    public function getMiniGameData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getMiniGameData($charId, $sessionKey, ...$args);
    }

    public function getVillageData($charId = null, $sessionKey = null, ...$args)
    {
        return $this->crew->getVillageData($charId, $sessionKey, ...$args);
    }
}
