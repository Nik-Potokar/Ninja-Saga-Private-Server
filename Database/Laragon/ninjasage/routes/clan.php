<?php

use App\Http\Controllers\ClanApiController;
use App\Http\Middleware\ClanAuth;
use Illuminate\Support\Facades\Route;

// Public endpoints (no auth required)
Route::post('/season', [ClanApiController::class, 'season']);
Route::post('/auth/login', [ClanApiController::class, 'login']);

// All other endpoints require Bearer token authentication
Route::middleware(ClanAuth::class)->group(function () {

    // Player
    Route::post('/player/clan',                [ClanApiController::class, 'playerClan']);
    Route::post('/player/stamina',             [ClanApiController::class, 'playerStamina']);
    Route::post('/player/stamina/upgrade-max', [ClanApiController::class, 'upgradeMaxStamina']);
    Route::post('/player/stamina/refill',      [ClanApiController::class, 'refillStamina']);
    Route::post('/player/clan-members',        [ClanApiController::class, 'clanMembers']);
    Route::post('/player/quit',                [ClanApiController::class, 'quitClan']);
    Route::post('/player/boost-prestige',      [ClanApiController::class, 'boostPrestige']);
    Route::post('/player/request/{clanId}',    [ClanApiController::class, 'sendRequest']);
    Route::post('/player/donate/{amount}/golds',  [ClanApiController::class, 'donateGolds']);
    Route::post('/player/donate/{amount}/tokens', [ClanApiController::class, 'donateTokens']);
    Route::post('/player/buy-onigiri/{id}',    [ClanApiController::class, 'buyOnigiriPackage']);

    // History
    Route::post('/history',          [ClanApiController::class, 'history']);
    Route::post('/season-histories', [ClanApiController::class, 'seasonHistories']);

    // Battle
    Route::post('/battle/opponents',              [ClanApiController::class, 'battleOpponents']);
    Route::post('/battle/opponents/{id}',         [ClanApiController::class, 'searchBattleOpponent']);
    Route::post('/battle/defenders',              [ClanApiController::class, 'battleDefenders']);
    Route::post('/battle/quick/{clanId}',         [ClanApiController::class, 'quickAttack']);
    Route::post('/battle/manual/start/{clanId}',  [ClanApiController::class, 'startManualAttack']);
    Route::post('/battle/manual/finish',          [ClanApiController::class, 'finishManualAttack']);

    // Join requests
    Route::post('/request/available',        [ClanApiController::class, 'availableClans']);
    Route::post('/request/available/{id}',   [ClanApiController::class, 'searchAvailableClan']);
    Route::post('/request/all',              [ClanApiController::class, 'memberRequests']);
    Route::post('/request/all/reject',       [ClanApiController::class, 'rejectAllRequests']);
    Route::post('/request/{id}/accept',      [ClanApiController::class, 'acceptRequest']);
    Route::post('/request/{id}/reject',      [ClanApiController::class, 'rejectRequest']);

    // Clan management
    Route::post('/create',                           [ClanApiController::class, 'createClan']);
    Route::post('/rename',                           [ClanApiController::class, 'renameClan']);
    Route::post('/swap-master',                      [ClanApiController::class, 'swapMaster']);
    Route::post('/upgrade-building/{id}',            [ClanApiController::class, 'upgradeBuilding']);
    Route::post('/member/increase-max-members',      [ClanApiController::class, 'increaseMaxMembers']);
    Route::post('/announcement/save',                [ClanApiController::class, 'saveAnnouncement']);
    Route::post('/announcement/publish',             [ClanApiController::class, 'publishAnnouncement']);
    Route::post('/invite-character/{id}',            [ClanApiController::class, 'inviteCharacter']);

    // Members
    Route::post('/member/{id}/kick',           [ClanApiController::class, 'kickMember']);
    Route::post('/member/{id}/promote-elder',  [ClanApiController::class, 'promoteElder']);
    Route::post('/member/{id}/onigiri/limit',  [ClanApiController::class, 'getOnigiriInfo']);
    Route::post('/member/{id}/onigiri/gift/{amount}', [ClanApiController::class, 'giveOnigiri']);
});
