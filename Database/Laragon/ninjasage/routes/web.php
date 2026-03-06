<?php

use App\Models\Skill;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AmfController;
use App\Http\Controllers\ClanApiController;
use App\Http\Controllers\CrewApiController;

Route::get('/', function () {
    return redirect('/admin');
});

// AMF Gateway Routes
Route::any('/amf', [AmfController::class, 'handle']);
Route::any('/gateway.php', [AmfController::class, 'handle']);

// Enemy SWF files — loaded by the Flash client as https://ninjasage.test/enemy/ene_XXXX.swf
// Resolves to ENEMY_SWF_PATH env var, defaulting to Client/enemy/ relative to this repo.
Route::get('/enemy/{filename}', function (string $filename) {
    if (!preg_match('/^[a-zA-Z0-9_-]+\.swf$/', $filename)) {
        abort(404);
    }
    $basePath = env('ENEMY_SWF_PATH', realpath(base_path() . '/../../../Client/enemy'));
    if (!$basePath || !is_dir($basePath)) {
        abort(404);
    }
    $path = $basePath . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'application/x-shockwave-flash']);
})->where('filename', '[a-zA-Z0-9_-]+\.swf');

// Skill SWF files — loaded by the Flash client as https://ninjasage.test/skills/skill_XXXX.swf
// Resolves to SKILL_SWF_PATH env var, defaulting to Client/skills/ relative to this repo.
// If the exact SWF does not exist (e.g. a custom skill), falls back to SKILL_DEFAULT_SWF
// (default: skill_01.swf) so the inventory panel can still render the slot.
Route::get('/skills/{filename}', function (string $filename) {
    if (!preg_match('/^[a-zA-Z0-9_-]+\.swf$/', $filename)) {
        abort(404);
    }
    $basePath = env('SKILL_SWF_PATH', realpath(base_path() . '/../../../Client/skills'));
    if (!$basePath || !is_dir($basePath)) {
        abort(404);
    }
    $path = $basePath . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($path)) {
        // Check if the skill has a base SWF alias set in the database (e.g. "skill_59")
        $skillId   = pathinfo($filename, PATHINFO_FILENAME); // e.g. "skill_10002"
        $swfAlias  = Skill::where('skill_id', $skillId)->value('swf'); // e.g. "skill_59"
        $candidate = $swfAlias ? ($swfAlias . '.swf') : env('SKILL_DEFAULT_SWF', 'skill_01.swf');
        $fallback  = $basePath . DIRECTORY_SEPARATOR . $candidate;
        if (!file_exists($fallback)) {
            abort(404);
        }
        $path = $fallback;
    }
    return response()->file($path, ['Content-Type' => 'application/x-shockwave-flash']);
})->where('filename', '[a-zA-Z0-9_-]+\.swf');

// Clan API Routes
Route::prefix('clan')->group(function () {
    // Public (no token needed)
    Route::post('/auth/login',    [ClanApiController::class, 'login']);
    Route::post('/season',        [ClanApiController::class, 'season']);

    // Protected (require clan auth token)
    Route::middleware('clan.auth')->group(function () {
        Route::post('/player/clan',                      [ClanApiController::class, 'playerClan']);
        Route::post('/player/stamina',                   [ClanApiController::class, 'playerStamina']);
        Route::post('/player/request/{clanId}',          [ClanApiController::class, 'sendRequest']);
        Route::post('/player/clan-members',              [ClanApiController::class, 'clanMembers']);
        Route::post('/player/quit',                      [ClanApiController::class, 'quitClan']);
        Route::post('/player/donate/{amount}/golds',     [ClanApiController::class, 'donateGolds']);
        Route::post('/player/donate/{amount}/tokens',    [ClanApiController::class, 'donateTokens']);
        Route::post('/player/stamina/upgrade-max',       [ClanApiController::class, 'upgradeMaxStamina']);
        Route::post('/player/stamina/refill',            [ClanApiController::class, 'refillStamina']);
        Route::post('/player/boost-prestige',            [ClanApiController::class, 'boostPrestige']);
        Route::post('/player/buy-onigiri/{id}',          [ClanApiController::class, 'buyOnigiriPackage']);

        Route::post('/history',                          [ClanApiController::class, 'history']);
        Route::post('/create',                           [ClanApiController::class, 'createClan']);
        Route::post('/rename',                           [ClanApiController::class, 'renameClan']);
        Route::post('/swap-master',                      [ClanApiController::class, 'swapMaster']);
        Route::post('/invite-character/{id}',            [ClanApiController::class, 'inviteCharacter']);
        Route::post('/season-histories',                 [ClanApiController::class, 'seasonHistories']);
        Route::post('/upgrade-building/{id}',            [ClanApiController::class, 'upgradeBuilding']);
        Route::post('/announcement/save',                [ClanApiController::class, 'saveAnnouncement']);
        Route::post('/announcement/publish',             [ClanApiController::class, 'publishAnnouncement']);

        Route::post('/battle/opponents',                 [ClanApiController::class, 'battleOpponents']);
        Route::post('/battle/opponents/{id}',            [ClanApiController::class, 'searchBattleOpponent']);
        Route::post('/battle/defenders',                 [ClanApiController::class, 'battleDefenders']);
        Route::post('/battle/quick/{clanId}',            [ClanApiController::class, 'quickAttack']);
        Route::post('/battle/manual/start/{clanId}',     [ClanApiController::class, 'startManualAttack']);
        Route::post('/battle/manual/finish',             [ClanApiController::class, 'finishManualAttack']);

        // Literal routes before dynamic {id} routes to avoid conflicts
        Route::post('/request/all/reject',               [ClanApiController::class, 'rejectAllRequests']);
        Route::post('/request/all',                      [ClanApiController::class, 'memberRequests']);
        Route::post('/request/available',                [ClanApiController::class, 'availableClans']);
        Route::post('/request/available/{id}',           [ClanApiController::class, 'searchAvailableClan']);
        Route::post('/request/{id}/reject',              [ClanApiController::class, 'rejectRequest']);
        Route::post('/request/{id}/accept',              [ClanApiController::class, 'acceptRequest']);

        Route::post('/member/increase-max-members',      [ClanApiController::class, 'increaseMaxMembers']);
        Route::post('/member/{id}/kick',                 [ClanApiController::class, 'kickMember']);
        Route::post('/member/{id}/promote-elder',        [ClanApiController::class, 'promoteElder']);
        Route::post('/member/{id}/onigiri/limit',        [ClanApiController::class, 'getOnigiriInfo']);
        Route::post('/member/{id}/onigiri/gift/{amount}',[ClanApiController::class, 'giveOnigiri']);
    });
});

// Crew API Routes — prefixed at /crew/* (when server domain serves both clan+crew)
// AND at root level (when crew.ninjasage.id proxies here without /crew prefix).
// Register a shared helper so both groups use identical route definitions.
$crewRoutes = function () {
    // Public
    Route::post('/season',        [CrewApiController::class, 'season']);
    Route::post('/auth/login',    [CrewApiController::class, 'login']);

    // Protected
    Route::middleware('crew.auth')->group(function () {
        Route::post('/season/pool',                          [CrewApiController::class, 'tokenPool']);
        Route::post('/season/previous',                      [CrewApiController::class, 'previousSeason']);
        Route::post('/season-histories',                     [CrewApiController::class, 'seasonHistories']);

        Route::post('/player/crew',                          [CrewApiController::class, 'playerCrew']);
        Route::post('/player/crew/members',                  [CrewApiController::class, 'crewMembers']);
        Route::post('/player/stamina',                       [CrewApiController::class, 'playerStamina']);
        Route::post('/player/stamina/upgrade-max',           [CrewApiController::class, 'upgradeMaxStamina']);
        Route::post('/player/stamina/refill',                [CrewApiController::class, 'refillStamina']);
        Route::post('/player/boost-prestige',                [CrewApiController::class, 'boostPrestige']);
        Route::post('/player/buy-onigiri/{id}',              [CrewApiController::class, 'buyOnigiriPackage']);
        Route::post('/player/quit',                          [CrewApiController::class, 'quitCrew']);
        Route::post('/player/kick/{id}',                     [CrewApiController::class, 'kickMember']);
        Route::post('/player/promote-elder/{id}',            [CrewApiController::class, 'promoteElder']);
        Route::post('/player/donate/{amount}/golds',         [CrewApiController::class, 'donateGolds']);
        Route::post('/player/donate/{amount}/tokens',        [CrewApiController::class, 'donateTokens']);
        Route::post('/player/switch-master/{id}',            [CrewApiController::class, 'switchMaster']);
        Route::post('/player/minigame',                      [CrewApiController::class, 'miniGame']);
        Route::post('/player/minigame/start',                [CrewApiController::class, 'startMiniGame']);
        Route::post('/player/minigame/finish',               [CrewApiController::class, 'finishMiniGame']);
        Route::post('/player/minigame/buy/{type}',           [CrewApiController::class, 'buyMiniGame']);
        Route::post('/player/request/{crewId}',              [CrewApiController::class, 'sendRequest']);

        Route::post('/history',                              [CrewApiController::class, 'history']);
        Route::post('/create',                               [CrewApiController::class, 'createCrew']);
        Route::post('/rename',                               [CrewApiController::class, 'renameCrew']);

        Route::post('/upgrade/building/{id}',                [CrewApiController::class, 'upgradeBuilding']);
        Route::post('/upgrade/max-members',                  [CrewApiController::class, 'increaseMaxMembers']);

        Route::post('/announcements',                        [CrewApiController::class, 'saveAnnouncement']);
        Route::post('/announcement/publish',                 [CrewApiController::class, 'publishAnnouncement']);

        // Literal routes before dynamic {id} to avoid conflicts
        Route::post('/request/all/reject',                   [CrewApiController::class, 'rejectAllRequests']);
        Route::post('/request/all',                          [CrewApiController::class, 'memberRequests']);
        Route::post('/request/available',                    [CrewApiController::class, 'availableCrews']);
        Route::post('/request/available/{id}',               [CrewApiController::class, 'searchAvailableCrew']);
        Route::post('/request/{id}/reject',                  [CrewApiController::class, 'rejectRequest']);
        Route::post('/request/{id}/accept',                  [CrewApiController::class, 'acceptRequest']);
        Route::post('/request/{id}/invite',                  [CrewApiController::class, 'inviteCharacter']);

        Route::post('/member/{id}/onigiri/limit',            [CrewApiController::class, 'getOnigiriInfo']);
        Route::post('/member/{id}/onigiri/gift/{amount}',    [CrewApiController::class, 'giveOnigiri']);

        // Battle castle routes — literal before dynamic
        Route::post('/battle/castles/{id}/ranks',            [CrewApiController::class, 'castleRanks']);
        Route::post('/battle/castles/{id}/recovery',         [CrewApiController::class, 'castleRecovery']);
        Route::post('/battle/castles/{id}/recover',          [CrewApiController::class, 'castleRecover']);
        Route::post('/battle/castles/{id}/defenders',        [CrewApiController::class, 'castleDefenders']);
        Route::post('/battle/castles/{id}',                  [CrewApiController::class, 'getCastle']);
        Route::post('/battle/castles',                       [CrewApiController::class, 'getCastles']);
        Route::post('/battle/role/switch/{id}',              [CrewApiController::class, 'switchRole']);
        Route::post('/battle/attackers',                     [CrewApiController::class, 'battleAttackers']);
        Route::post('/battle/opponents',                     [CrewApiController::class, 'battleOpponents']);
        Route::post('/battle/opponents/{id}',                [CrewApiController::class, 'searchBattleOpponent']);
        Route::post('/battle/phase1/start',                  [CrewApiController::class, 'startPhaseOneBattle']);
        Route::post('/battle/phase1/finish',                 [CrewApiController::class, 'finishPhaseOneBattle']);
        Route::post('/battle/phase2/start',                  [CrewApiController::class, 'startPhaseTwoBattle']);
        Route::post('/battle/phase2/finish',                 [CrewApiController::class, 'finishPhaseTwoBattle']);
    });
};

// /crew/* — for when the main server handles both domains under one app
Route::prefix('crew')->group($crewRoutes);

// Root-level /* — for when crew.ninjasage.id proxies directly to this Laravel app
// (Nginx: proxy_pass http://127.0.0.1:<port>  without stripping the /crew prefix)
Route::group([], $crewRoutes);