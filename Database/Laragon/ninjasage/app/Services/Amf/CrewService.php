<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

/**
 * CrewService — stub for the Crew/Clan system.
 *
 * The Flash client calls these AMF methods when the player opens any
 * Crew / Clan panel (CrewHall, CrewVillage, CrewBattle, etc.).
 * Without this service the controller throws an exception and the client
 * shows "unable to login to clan server code:422".
 *
 * All methods return a consistent "not available" or empty-crew response
 * so the client can display the UI gracefully instead of crashing.
 */
class CrewService
{
    use ValidatesSession;

    // ------------------------------------------------------------------
    // Login / Init
    // ------------------------------------------------------------------

    /** Called when the client first opens the Crew panel. */
    public function login($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.login: Char $charId");
        return $this->notInCrew();
    }

    public function loginToClanServer($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.loginToClanServer: Char $charId");
        return $this->notInCrew();
    }

    public function loginToCrewServer($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.loginToCrewServer: Char $charId");
        return $this->notInCrew();
    }

    public function init($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.init: Char $charId");
        return $this->notInCrew();
    }

    public function initialize($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.initialize: Char $charId");
        return $this->notInCrew();
    }

    public function connect($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.connect: Char $charId");
        return $this->notInCrew();
    }

    // ------------------------------------------------------------------
    // Crew data
    // ------------------------------------------------------------------

    public function getCrewData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getCrewData: Char $charId");
        return $this->notInCrew();
    }

    public function getClanData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getClanData: Char $charId");
        return $this->notInCrew();
    }

    public function getCrewInfo($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getCrewInfo: Char $charId");
        return $this->notInCrew();
    }

    public function getClanInfo($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getClanInfo: Char $charId");
        return $this->notInCrew();
    }

    public function getData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getData: Char $charId");
        return $this->notInCrew();
    }

    public function getInfo($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getInfo: Char $charId");
        return $this->notInCrew();
    }

    public function getStatus($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getStatus: Char $charId");
        return $this->notInCrew();
    }

    // ------------------------------------------------------------------
    // Crew list / search
    // ------------------------------------------------------------------

    public function getAllClans($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getAllClans");
        return ['status' => 1, 'error' => 0, 'clans' => [], 'crews' => [], 'list' => []];
    }

    public function getCrewList($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getCrewList");
        return ['status' => 1, 'error' => 0, 'clans' => [], 'crews' => [], 'list' => []];
    }

    public function getClanList($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getClanList");
        return ['status' => 1, 'error' => 0, 'clans' => [], 'crews' => [], 'list' => []];
    }

    public function searchClan($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.searchClan");
        return ['status' => 1, 'error' => 0, 'clans' => [], 'crews' => [], 'list' => []];
    }

    public function searchCrew($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.searchCrew");
        return ['status' => 1, 'error' => 0, 'clans' => [], 'crews' => [], 'list' => []];
    }

    // ------------------------------------------------------------------
    // Create / Join / Leave
    // ------------------------------------------------------------------

    public function createClan($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.createClan: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    public function createCrew($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.createCrew: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    public function joinClan($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.joinClan: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    public function joinCrew($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.joinCrew: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    public function leaveClan($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.leaveClan: Char $charId");
        return ['status' => 1, 'error' => 0];
    }

    public function leaveCrew($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.leaveCrew: Char $charId");
        return ['status' => 1, 'error' => 0];
    }

    public function disbandClan($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.disbandClan: Char $charId");
        return ['status' => 1, 'error' => 0];
    }

    public function disbandCrew($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.disbandCrew: Char $charId");
        return ['status' => 1, 'error' => 0];
    }

    // ------------------------------------------------------------------
    // Members
    // ------------------------------------------------------------------

    public function getMembers($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getMembers");
        return ['status' => 1, 'error' => 0, 'members' => []];
    }

    public function getClanMembers($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getClanMembers");
        return ['status' => 1, 'error' => 0, 'members' => []];
    }

    public function kickMember($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.kickMember: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    public function promoteMember($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.promoteMember: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    // ------------------------------------------------------------------
    // Leaderboard / Battle
    // ------------------------------------------------------------------

    public function getLeaderboard($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getLeaderboard");
        return ['status' => 1, 'error' => 0, 'data' => [], 'list' => []];
    }

    public function getCrewBattleData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getCrewBattleData");
        return ['status' => 1, 'error' => 0, 'data' => []];
    }

    public function getClanBattleData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getClanBattleData");
        return ['status' => 1, 'error' => 0, 'data' => []];
    }

    public function startBattle($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.startBattle: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    public function finishBattle($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.finishBattle: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    // ------------------------------------------------------------------
    // Mini game / Village
    // ------------------------------------------------------------------

    public function getMiniGameData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getMiniGameData");
        return ['status' => 1, 'error' => 0, 'data' => []];
    }

    public function getVillageData($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.getVillageData");
        return ['status' => 1, 'error' => 0, 'data' => []];
    }

    public function upgradeVillage($charId = null, $sessionKey = null, ...$args)
    {
        Log::info("CrewService.upgradeVillage: Char $charId");
        return ['status' => 2, 'error' => 0, 'result' => 'Crew system is not available on this server.'];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Standard "not in a crew" response — status 1 so the client doesn't error. */
    private function notInCrew(): array
    {
        return [
            'status'    => 1,
            'error'     => 0,
            'clan'      => null,
            'crew'      => null,
            'clan_id'   => 0,
            'crew_id'   => 0,
            'clan_name' => '',
            'crew_name' => '',
            'result'    => 'not_in_crew',
        ];
    }
}
