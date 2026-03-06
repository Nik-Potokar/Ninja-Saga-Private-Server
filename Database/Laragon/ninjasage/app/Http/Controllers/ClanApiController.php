<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterMail;
use App\Models\Clan;
use App\Models\ClanAuthToken;
use App\Models\ClanBattle;
use App\Models\ClanMember;
use App\Models\ClanRequest;
use App\Models\ClanSeason;
use App\Models\GameConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClanApiController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /clan/season
    // -------------------------------------------------------------------------
    public function season(): JsonResponse
    {
        $season = $this->activeSeason();
        $now    = now()->timestamp;

        // Use ended_at if set (admin-configured season end), otherwise fall back
        // to a daily cycle so the timer is never stuck at zero.
        if ($season->ended_at) {
            $countdown = max(0, $season->ended_at->timestamp - $now);
        } else {
            $countdown = 86400 - ($now % 86400);
        }

        return response()->json([
            'id'        => $season->id,
            'number'    => $season->number,
            'active'    => true,
            'timestamp' => $countdown,
            'season'    => $season->number,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/auth/login  { char_id, session_key }
    // -------------------------------------------------------------------------
    public function login(Request $request): JsonResponse
    {
        $charId     = $request->input('char_id');
        $sessionKey = trim((string) $request->input('session_key'));

        Log::info('Clan login attempt', [
            'char_id'             => $charId,
            'session_key_len'     => strlen($sessionKey),
            'session_key_preview' => substr($sessionKey, 0, 8),
            'content_type'        => $request->header('Content-Type'),
            'all_input'           => $request->all(),
        ]);

        if (!$charId || !$sessionKey) {
            Log::warning('Clan login: missing credentials');
            return response()->json(['status' => 0, 'errorMessage' => 'Missing credentials'], 422);
        }

        $character = Character::find($charId);
        if (!$character) {
            Log::warning('Clan login: character not found', ['char_id' => $charId]);
            return response()->json(['status' => 0, 'errorMessage' => 'Invalid character'], 401);
        }

        $user = User::find($character->user_id);
        if (!$user) {
            Log::warning('Clan login: user not found for character', ['char_id' => $charId]);
            return response()->json(['status' => 0, 'errorMessage' => 'Invalid session key'], 401);
        }

        if (!$this->sessionKeyMatches($user->id, (string) $user->session_key, $sessionKey)) {
            Log::warning('Clan login: session key mismatch', [
                'user_id'          => $user->id,
                'stored_preview'   => substr((string) $user->session_key, 0, 8),
                'provided_preview' => substr($sessionKey, 0, 8),
            ]);
            return response()->json(['status' => 0, 'errorMessage' => 'Invalid session key'], 401);
        }

        // Replace any existing token for this character
        ClanAuthToken::where('character_id', $charId)->delete();
        $token = bin2hex(random_bytes(32));
        ClanAuthToken::create([
            'user_id'      => $user->id,
            'character_id' => $charId,
            'token'        => $token,
            'expires_at'   => now()->addHours(24),
        ]);

        Log::info('Clan login success', ['user_id' => $user->id, 'char_id' => $charId]);

        // Flash clanOnAuth checks for 'access_token' (not 'token')
        return response()->json(['status' => 1, 'access_token' => $token]);
    }

    private function sessionKeyMatches(int $userId, string $stored, string $provided): bool
    {
        $stored   = trim($stored);
        $provided = trim($provided);

        if (hash_equals($stored, $provided)) {
            return true;
        }
        $decoded = base64_decode($provided, true);
        if ($decoded !== false && hash_equals($stored, $decoded)) {
            return true;
        }
        if (hash_equals(hash('sha256', $userId . $stored), $provided)) {
            return true;
        }
        if (hash_equals(md5($userId . $stored), $provided)) {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/clan
    // -------------------------------------------------------------------------
    public function playerClan(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['clan' => null]);
        }

        $clan = Clan::with('members')->find($member->clan_id);
        // Flash clanOnGetData checks hasOwnProperty("clan") && hasOwnProperty("char")
        // Missing either field sends the player to ClanCreate instead of ClanVillage
        return response()->json([
            'clan' => $this->formatClan($clan, $charId),
            'char' => $this->formatMember($member),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/stamina
    // -------------------------------------------------------------------------
    public function playerStamina(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['stamina' => 0, 'max_stamina' => 5]);
        }

        // Flash onGetStamina checks param1.hasOwnProperty("char") then reads param1.char.stamina
        return response()->json([
            'char' => [
                'stamina'        => $member->stamina,
                'max_stamina'    => $member->max_stamina,
                'prestige'       => 0,
                'prestige_boost' => $this->prestigeBoostRemaining($member),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/history
    // -------------------------------------------------------------------------
    public function history(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['history' => []]);
        }

        $clanId  = $member->clan_id;
        $battles = ClanBattle::where('attacker_clan_id', $clanId)
            ->orWhere('defender_clan_id', $clanId)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($b) => [
                'id'               => $b->id,
                'attacker_clan_id' => $b->attacker_clan_id,
                'defender_clan_id' => $b->defender_clan_id,
                'attacker_won'     => $b->attacker_won,
                'created_at'       => $b->created_at?->toIso8601String(),
            ]);

        // Flash onGetLatestHistory checks param1.hasOwnProperty("histories")
        return response()->json(['histories' => $battles]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/battle/opponents
    // -------------------------------------------------------------------------
    public function battleOpponents(Request $request): JsonResponse
    {
        $charId    = $request->attributes->get('char_id');
        $member    = ClanMember::where('character_id', $charId)->first();
        $ownClanId = $member?->clan_id;

        $clans = Clan::with('members')
            ->when($ownClanId, fn($q) => $q->where('id', '!=', $ownClanId))
            ->orderByDesc('prestige')
            ->limit(20)
            ->get()
            ->map(fn($c) => $this->formatClanSummary($c));

        return response()->json(['clans' => $clans]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/battle/opponents/{id}
    // -------------------------------------------------------------------------
    public function searchBattleOpponent(Request $request, $id): JsonResponse
    {
        // Flash onGetSearchClansRes checks param1.hasOwnProperty("clans") — must wrap in array
        $clan = Clan::with('members')->find($id);
        if (!$clan) {
            return response()->json(['clans' => []]);
        }
        return response()->json(['clans' => [$this->formatClanSummary($clan)]]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/request/available
    // -------------------------------------------------------------------------
    public function availableClans(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');

        $excludedClanIds = ClanRequest::where('character_id', $charId)
            ->where('status', 'pending')
            ->pluck('clan_id');

        $inClan = ClanMember::where('character_id', $charId)->value('clan_id');
        if ($inClan) {
            $excludedClanIds = $excludedClanIds->push($inClan);
        }

        $clans = Clan::with('members')
            ->whereNotIn('id', $excludedClanIds)
            ->orderByDesc('prestige')
            ->limit(20)
            ->get()
            ->map(fn($c) => $this->formatClanSummary($c));

        return response()->json(['clans' => $clans]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/request/available/{id}
    // -------------------------------------------------------------------------
    public function searchAvailableClan(Request $request, $id): JsonResponse
    {
        $clan = Clan::with('members')->find($id);
        if (!$clan) {
            return response()->json(['clan' => null]);
        }
        return response()->json(['clan' => $this->formatClanSummary($clan)]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/request/{clanId}
    // -------------------------------------------------------------------------
    public function sendRequest(Request $request, $clanId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');

        if (ClanMember::where('character_id', $charId)->exists()) {
            return response()->json(['errorMessage' => 'Already in a clan'], 422);
        }

        $clan = Clan::find($clanId);
        if (!$clan) {
            return response()->json(['errorMessage' => 'Clan not found'], 404);
        }

        $existing = ClanRequest::where('character_id', $charId)
            ->where('clan_id', $clanId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['errorMessage' => 'Request already sent'], 422);
        }

        ClanRequest::create([
            'clan_id'      => $clanId,
            'character_id' => $charId,
            'status'       => 'pending',
        ]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/clan-members
    // -------------------------------------------------------------------------
    public function clanMembers(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['members' => []]);
        }

        $members = ClanMember::where('clan_id', $member->clan_id)->get()
            ->map(fn($m) => $this->formatMember($m));

        return response()->json(['members' => $members]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/request/all
    // -------------------------------------------------------------------------
    public function memberRequests(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['requests' => []]);
        }

        $requests = ClanRequest::with('character')
            ->where('clan_id', $member->clan_id)
            ->where('status', 'pending')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'character_id'    => $r->character_id,
                'character_name'  => $r->character?->name ?? 'Unknown',
                'character_level' => $r->character?->level ?? 1,
                'created_at'      => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['requests' => $requests]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/request/{id}/reject
    // -------------------------------------------------------------------------
    public function rejectRequest(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        ClanRequest::where('id', $id)
            ->where('clan_id', $member->clan_id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/request/all/reject
    // -------------------------------------------------------------------------
    public function rejectAllRequests(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        ClanRequest::where('clan_id', $member->clan_id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/request/{id}/accept
    // -------------------------------------------------------------------------
    public function acceptRequest(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        $joinRequest = ClanRequest::where('id', $id)
            ->where('clan_id', $member->clan_id)
            ->where('status', 'pending')
            ->first();

        if (!$joinRequest) {
            return response()->json(['errorMessage' => 'Request not found'], 404);
        }

        $clan         = Clan::find($member->clan_id);
        $currentCount = ClanMember::where('clan_id', $member->clan_id)->count();

        if ($currentCount >= $clan->max_members) {
            return response()->json(['errorMessage' => 'Clan is full'], 422);
        }

        $clanSettings   = GameConfig::get('clan_settings', []);
        $initialStamina = (int) ($clanSettings['initial_stamina'] ?? 5);

        DB::transaction(function () use ($joinRequest, $member, $initialStamina) {
            ClanMember::where('character_id', $joinRequest->character_id)->delete();
            ClanMember::create([
                'clan_id'      => $member->clan_id,
                'character_id' => $joinRequest->character_id,
                'role'         => 'member',
                'stamina'      => $initialStamina,
                'max_stamina'  => $initialStamina,
            ]);
            $joinRequest->update(['status' => 'accepted']);
            ClanRequest::where('character_id', $joinRequest->character_id)
                ->where('id', '!=', $joinRequest->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);
        });

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/quit
    // -------------------------------------------------------------------------
    public function quitClan(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a clan'], 422);
        }
        if ($member->role === 'master') {
            return response()->json(['errorMessage' => 'Master cannot quit. Transfer leadership first.'], 422);
        }

        $member->delete();
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/member/{id}/kick
    // -------------------------------------------------------------------------
    public function kickMember(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $actor  = ClanMember::where('character_id', $charId)->first();

        if (!$actor || !in_array($actor->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        $target = ClanMember::where('clan_id', $actor->clan_id)
            ->where('character_id', $id)
            ->first();

        if (!$target) {
            return response()->json(['errorMessage' => 'Member not found'], 404);
        }
        if ($target->role === 'master') {
            return response()->json(['errorMessage' => 'Cannot kick the master'], 422);
        }
        if ($actor->role === 'elder' && $target->role === 'elder') {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        $target->delete();
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/swap-master
    // -------------------------------------------------------------------------
    public function swapMaster(Request $request): JsonResponse
    {
        $charId      = $request->attributes->get('char_id');
        $newMasterId = $request->input('character_id');

        $actor = ClanMember::where('character_id', $charId)->first();
        if (!$actor || $actor->role !== 'master') {
            return response()->json(['errorMessage' => 'Only master can transfer leadership'], 403);
        }

        $newMaster = ClanMember::where('clan_id', $actor->clan_id)
            ->where('character_id', $newMasterId)
            ->first();

        if (!$newMaster) {
            return response()->json(['errorMessage' => 'Member not found'], 404);
        }

        DB::transaction(function () use ($actor, $newMaster) {
            $actor->update(['role' => 'member']);
            $newMaster->update(['role' => 'master']);
            Clan::where('id', $actor->clan_id)->update(['master_id' => $newMaster->character_id]);
        });

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/member/{id}/promote-elder
    // -------------------------------------------------------------------------
    public function promoteElder(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $actor  = ClanMember::where('character_id', $charId)->first();

        if (!$actor || $actor->role !== 'master') {
            return response()->json(['errorMessage' => 'Only master can promote'], 403);
        }

        $target = ClanMember::where('clan_id', $actor->clan_id)
            ->where('character_id', $id)
            ->first();

        if (!$target) {
            return response()->json(['errorMessage' => 'Member not found'], 404);
        }

        $newRole = $target->role === 'elder' ? 'member' : 'elder';
        $target->update(['role' => $newRole]);

        return response()->json(['success' => true, 'role' => $newRole]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/donate/{amount}/golds
    // -------------------------------------------------------------------------
    public function donateGolds(Request $request, $amount): JsonResponse
    {
        $charId    = $request->attributes->get('char_id');
        $amount    = (int) $amount;
        $member    = ClanMember::where('character_id', $charId)->first();
        $character = Character::find($charId);

        if (!$member || !$character) {
            return response()->json(['errorMessage' => 'Not in a clan'], 422);
        }
        if ($character->gold < $amount) {
            return response()->json(['errorMessage' => 'Not enough gold'], 422);
        }

        DB::transaction(function () use ($character, $member, $amount) {
            $character->decrement('gold', $amount);
            $member->increment('donated_golds', $amount);
            Clan::where('id', $member->clan_id)->increment('gold', $amount);
        });

        return response()->json(['success' => true, 'gold' => $character->fresh()->gold]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/donate/{amount}/tokens
    // -------------------------------------------------------------------------
    public function donateTokens(Request $request, $amount): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $amount = (int) $amount;
        $member = ClanMember::where('character_id', $charId)->first();
        $user   = User::find($request->attributes->get('user_id'));

        if (!$member || !$user) {
            return response()->json(['errorMessage' => 'Not in a clan'], 422);
        }
        if ($user->tokens < $amount) {
            return response()->json(['errorMessage' => 'Not enough tokens'], 422);
        }

        DB::transaction(function () use ($user, $member, $amount) {
            $user->decrement('tokens', $amount);
            $member->increment('donated_tokens', $amount);
            Clan::where('id', $member->clan_id)->increment('tokens', $amount);
        });

        return response()->json(['success' => true, 'tokens' => $user->fresh()->tokens]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/upgrade-building/{id}
    // -------------------------------------------------------------------------
    public function upgradeBuilding(Request $request, $buildingId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        // Flash sends "clan_ramen", "clan_hot_spring", "clan_temple", "clan_training_hall"
        // Strip the "clan_" prefix to get the actual buildings array key
        $buildingKey = str_replace('clan_', '', $buildingId);
        $allowed     = ['ramen', 'hot_spring', 'temple', 'training_hall'];
        if (!in_array($buildingKey, $allowed)) {
            return response()->json(['errorMessage' => 'Invalid building'], 422);
        }

        $clan      = Clan::find($member->clan_id);
        $buildings = $clan->buildings ?? $clan->getDefaultBuildings();
        $level     = ($buildings[$buildingKey] ?? 0);

        if ($level >= 3) {
            return response()->json(['errorMessage' => 'Building is already at max level'], 422);
        }

        // Levels 0→1 and 1→2 cost clan gold; level 2→3 costs clan tokens
        // (matches ClanUpgrade.as: price_type.gotoAndStop(2) when param2 == 2)
        if ($level < 2) {
            $cost = ($level + 1) * 1000;
            if ($clan->gold < $cost) {
                return response()->json(['errorMessage' => 'Not enough clan gold']);
            }
            $buildings[$buildingKey] = $level + 1;
            $clan->update(['buildings' => $buildings, 'gold' => $clan->gold - $cost]);
        } else {
            $cost = 4000;
            if ($clan->tokens < $cost) {
                return response()->json(['errorMessage' => 'Not enough clan tokens']);
            }
            $buildings[$buildingKey] = $level + 1;
            $clan->update(['buildings' => $buildings, 'tokens' => $clan->tokens - $cost]);
        }

        // Flash onUpgradeRes ignores the body and calls getClanData on success;
        // just needs no "errorMessage" field and no HTTP error
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/announcement/save  { announcement }
    // -------------------------------------------------------------------------
    public function saveAnnouncement(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        Clan::where('id', $member->clan_id)->update([
            'announcement_draft' => $request->input('announcement', ''),
        ]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/announcement/publish
    // -------------------------------------------------------------------------
    public function publishAnnouncement(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        $clan = Clan::find($member->clan_id);
        $clan->update(['announcement_published' => $clan->announcement_draft]);

        // Flash onPublishedAnnouncement checks param1 == "ok"
        return response()->json("ok");
    }

    // -------------------------------------------------------------------------
    // POST /clan/member/increase-max-members
    // -------------------------------------------------------------------------
    public function increaseMaxMembers(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || $member->role !== 'master') {
            return response()->json(['errorMessage' => 'Only master can increase member cap'], 403);
        }

        $clan = Clan::find($member->clan_id);

        // Load admin-configurable clan settings (fall back to defaults matching Flash client)
        $clanSettings  = GameConfig::get('clan_settings', []);
        $maxMembersCap = (int) ($clanSettings['max_members_cap']      ?? 45);
        $costPerSlot   = (int) ($clanSettings['increase_members_cost'] ?? 10);

        $currentMax = (int) $clan->max_members;

        if ($currentMax >= $maxMembersCap) {
            return response()->json(['errorMessage' => 'Already at maximum member capacity'], 422);
        }

        // Mirror Flash client: increase by up to 10, cap at $maxMembersCap
        $increase = min(10, $maxMembersCap - $currentMax);
        $newMax   = $currentMax + $increase;

        // Cost = new_max * cost_per_slot (matches Flash: token_req = new_max * 10)
        $cost = $newMax * $costPerSlot;

        if ($clan->tokens < $cost) {
            return response()->json(['errorMessage' => 'Not enough clan tokens'], 422);
        }

        $clan->update([
            'max_members' => $newMax,
            'tokens'      => $clan->tokens - $cost,
        ]);

        return response()->json(['success' => true, 'max_members' => $newMax]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/stamina/upgrade-max
    // -------------------------------------------------------------------------
    public function upgradeMaxStamina(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a clan']);
        }

        $user = User::find($userId);

        $clanSettings  = GameConfig::get('clan_settings', []);
        $upgradeCost   = (int) ($clanSettings['stamina_upgrade_cost'] ?? 500);
        $upgradeStep   = (int) ($clanSettings['stamina_upgrade_step'] ?? 50);
        $maxStaminaCap = (int) ($clanSettings['max_stamina_cap']      ?? 200);

        $cost = $upgradeCost;
        if ($user->tokens < $cost) {
            return response()->json(['errorMessage' => 'Not enough tokens']);
        }

        $newMax = min($member->max_stamina + $upgradeStep, $maxStaminaCap);
        $user->decrement('tokens', $cost);
        $member->update([
            'max_stamina' => $newMax,
            'stamina'     => $newMax,
        ]);

        // Flash onUpgradeStaminaRes checks: if(param1 == "ok") { Character.account_tokens -= 500 }
        return response()->json('ok');
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/boost-prestige
    // -------------------------------------------------------------------------
    public function boostPrestige(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $member = ClanMember::where('character_id', $charId)->first();
        $user   = User::find($userId);

        if (!$member || !$user) {
            return response()->json(['errorMessage' => 'Not in a clan']);
        }

        // Prestige boost costs tokens (Flash updates Character.account_tokens on success)
        $cost = 100;
        if ($user->tokens < $cost) {
            return response()->json(['errorMessage' => 'Not enough tokens']);
        }

        // Boost lasts 24 hours; duration returned in seconds so Flash timer can count down
        $boostSeconds = 86400;
        $expiresAt    = now()->addSeconds($boostSeconds);

        DB::transaction(function () use ($user, $member, $cost, $expiresAt) {
            $user->decrement('tokens', $cost);
            $member->update(['prestige_boost_expires_at' => $expiresAt]);
        });

        // Flash onBoostPrestigeRes checks hasOwnProperty("tokens") then reads:
        //   Character.account_tokens = param1.tokens
        //   clanVillage.char_data.prestige_boost = param1.prestige_boost
        //   main.showMessage(param1.result)
        return response()->json([
            'tokens'         => $user->fresh()->tokens,
            'prestige_boost' => $boostSeconds,
            'result'         => 'Prestige Boost activated for 24 hours!',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/stamina/refill
    // -------------------------------------------------------------------------
    public function refillStamina(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a clan']);
        }

        // Flash confirmation says "restore 50 Stamina"; client handles roll/token deduction
        $newStamina = min($member->stamina + 50, $member->max_stamina);
        $member->update(['stamina' => $newStamina]);

        // Flash onRestoreStaminaRes checks: if(param1 == "ok") { deduct client-side; refreshData() }
        return response()->json('ok');
    }

    // -------------------------------------------------------------------------
    // POST /clan/battle/defenders
    // -------------------------------------------------------------------------
    public function battleDefenders(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['defenders' => []]);
        }

        $defenders = ClanMember::where('clan_id', $member->clan_id)
            ->get()
            ->map(fn($m) => $this->formatMember($m));

        // Flash ClanRecruitManual.showMembersInfo checks param1.hasOwnProperty("members")
        return response()->json(['members' => $defenders]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/battle/quick/{clanId}
    // -------------------------------------------------------------------------
    public function quickAttack(Request $request, $clanId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a clan']);
        }

        // Flash requires >= 10 stamina before allowing the battle button (ClanBattle.as:311)
        if ($member->stamina < 10) {
            return response()->json(['errorMessage' => 'Not enough stamina']);
        }

        $defenderClan = Clan::find($clanId);
        if (!$defenderClan) {
            return response()->json(['errorMessage' => 'Clan not found']);
        }

        $attackerClan  = Clan::find($member->clan_id);
        $won           = $attackerClan->prestige >= $defenderClan->prestige;
        $cfg           = GameConfig::get('prestige_settings', []);
        $winRepGain    = (int) ($cfg['clan_win_clan_reputation']  ?? 10);
        $loseRepLoss   = (int) ($cfg['clan_lose_clan_reputation'] ?? 10);
        $repGain       = $won ? $winRepGain : 0;
        $defenderLoss  = $won ? $loseRepLoss : 0;
        $prestigeGain  = $won ? (int) ($cfg['clan_win_char_prestige']  ?? 5)
                              : (int) ($cfg['clan_lose_char_prestige'] ?? 1);   // character personal prestige increment

        DB::transaction(function () use ($member, $attackerClan, $defenderClan, $won, $repGain, $defenderLoss) {
            $member->decrement('stamina', 10);
            ClanBattle::create([
                'attacker_clan_id' => $attackerClan->id,
                'defender_clan_id' => $defenderClan->id,
                'season_id'        => $this->activeSeason()->id,
                'attacker_won'     => $won,
            ]);
            if ($won) {
                $attackerClan->increment('prestige', $repGain);
                // Clamp at 0 — prestige is UNSIGNED so raw decrement underflows
                $defenderClan->update(['prestige' => max(0, $defenderClan->prestige - $defenderLoss)]);
            }
        });

        $attackerClan->refresh();
        $defenderClan->refresh();

        // ClanBattleResults.updateDisplay reads: reputation, gain, stamina,
        // opponent_name, opponent_reputation, prestige
        return response()->json([
            'reputation'          => $attackerClan->prestige,
            'gain'                => $repGain,
            'stamina'             => $member->fresh()->stamina,
            'opponent_name'       => $defenderClan->name,
            'opponent_reputation' => $defenderClan->prestige,
            'prestige'            => $prestigeGain,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/battle/manual/start/{clanId}
    // -------------------------------------------------------------------------
    public function startManualAttack(Request $request, $clanId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a clan']);
        }
        // Flash already guards >=10 in ClanBattle.as; server also validates
        if ($member->stamina < 10) {
            return response()->json(['errorMessage' => 'Not enough stamina']);
        }

        $defenderClan = Clan::find($clanId);
        if (!$defenderClan) {
            return response()->json(['errorMessage' => 'Clan not found']);
        }

        // Pick up to 3 defender members as enemies for the combat engine
        $enemyIds = ClanMember::where('clan_id', $clanId)
            ->limit(3)
            ->pluck('character_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->toArray();

        $battleToken = bin2hex(random_bytes(16));

        // main.as startClanBattleRes checks param1.hasOwnProperty("enemies") to
        // start the BattleManager. "battle_id" becomes Character.battle_code.
        return response()->json([
            'battle_id' => $battleToken,
            'enemies'   => $enemyIds,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/battle/manual/finish
    // -------------------------------------------------------------------------
    public function finishManualAttack(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a clan']);
        }
        if ($member->stamina < 10) {
            return response()->json(['errorMessage' => 'Not enough stamina']);
        }

        $defenderClanId = $request->input('id');
        $defenderClan   = Clan::find($defenderClanId);
        if (!$defenderClan) {
            return response()->json(['errorMessage' => 'Opponent clan not found']);
        }

        // For manual battle the combat engine already ran client-side;
        // the result is always a win (the player fights until they win or lose
        // and only submits when the combat completes).
        $won          = true;
        $cfg          = GameConfig::get('prestige_settings', []);
        $repGain      = (int) ($cfg['clan_win_clan_reputation']  ?? 10);
        $defenderLoss = (int) ($cfg['clan_lose_clan_reputation'] ?? 10);
        $prestigeGain = (int) ($cfg['clan_win_char_prestige']    ?? 5);

        $attackerClan = Clan::find($member->clan_id);

        DB::transaction(function () use ($member, $attackerClan, $defenderClan, $won, $repGain, $defenderLoss, $request) {
            $member->decrement('stamina', 10);
            ClanBattle::create([
                'attacker_clan_id' => $attackerClan->id,
                'defender_clan_id' => $defenderClan->id,
                'season_id'        => $this->activeSeason()->id,
                'attacker_won'     => $won,
                'battle_data'      => $request->only('stats'),
            ]);
            $attackerClan->increment('prestige', $repGain);
            // Clamp at 0 — prestige is UNSIGNED so raw decrement underflows
            $defenderClan->update(['prestige' => max(0, $defenderClan->prestige - $defenderLoss)]);
        });

        $attackerClan->refresh();
        $defenderClan->refresh();

        // ClanBattleResults.updateDisplay reads: reputation, gain, stamina,
        // opponent_name, opponent_reputation, prestige
        return response()->json([
            'reputation'          => $attackerClan->prestige,
            'gain'                => $repGain,
            'stamina'             => $member->fresh()->stamina,
            'opponent_name'       => $defenderClan->name,
            'opponent_reputation' => $defenderClan->prestige,
            'prestige'            => $prestigeGain,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/create  { name }
    // -------------------------------------------------------------------------
    public function createClan(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $name   = trim($request->input('name', ''));

        if (strlen($name) < 3 || strlen($name) > 50) {
            return response()->json(['errorMessage' => 'Name must be 3-50 characters'], 422);
        }
        if (ClanMember::where('character_id', $charId)->exists()) {
            return response()->json(['errorMessage' => 'Already in a clan'], 422);
        }
        if (Clan::where('name', $name)->exists()) {
            return response()->json(['errorMessage' => 'Clan name already taken'], 422);
        }

        $clanSettings      = GameConfig::get('clan_settings', []);
        $initialStamina    = (int) ($clanSettings['initial_stamina']     ?? 5);
        $defaultMaxMembers = (int) ($clanSettings['default_max_members'] ?? 20);

        $clan = DB::transaction(function () use ($charId, $name, $initialStamina, $defaultMaxMembers) {
            $season = $this->activeSeason();
            $clan   = Clan::create([
                'season_id'   => $season->id,
                'name'        => $name,
                'master_id'   => $charId,
                'max_members' => $defaultMaxMembers,
                'buildings'   => (new Clan)->getDefaultBuildings(),
            ]);
            ClanMember::create([
                'clan_id'      => $clan->id,
                'character_id' => $charId,
                'role'         => 'master',
                'stamina'      => $initialStamina,
                'max_stamina'  => $initialStamina,
            ]);
            return $clan;
        });

        // Flash onCreateClanRes checks: if(param1 == "ok") — must return the bare string "ok"
        return response()->json("ok");
    }

    // -------------------------------------------------------------------------
    // POST /clan/rename  { name }
    // -------------------------------------------------------------------------
    public function renameClan(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $name   = trim($request->input('name', ''));
        $member = ClanMember::where('character_id', $charId)->first();

        if (!$member || $member->role !== 'master') {
            return response()->json(['errorMessage' => 'Only master can rename clan'], 403);
        }
        if (strlen($name) < 3 || strlen($name) > 50) {
            return response()->json(['errorMessage' => 'Name must be 3-50 characters'], 422);
        }
        if (Clan::where('name', $name)->where('id', '!=', $member->clan_id)->exists()) {
            return response()->json(['errorMessage' => 'Clan name already taken'], 422);
        }

        Clan::where('id', $member->clan_id)->update(['name' => $name]);
        // Flash onClanRenameRes checks param1 == "ok"
        return response()->json("ok");
    }

    // -------------------------------------------------------------------------
    // POST /clan/player/buy-onigiri/{id}
    // -------------------------------------------------------------------------
    public function buyOnigiriPackage(Request $request, $id): JsonResponse
    {
        $charId  = $request->attributes->get('char_id');
        $userId  = $request->attributes->get('user_id');

        // $id is the package index (0, 1, 2) sent by Flash
        $packages = [
            0 => ['qty' => 100,  'price' => 975],
            1 => ['qty' => 200,  'price' => 1900],
            2 => ['qty' => 500,  'price' => 4625],
        ];

        $pkg = $packages[(int)$id] ?? null;
        if (!$pkg) {
            return response()->json(['errorMessage' => 'Invalid package']);
        }

        $user = User::find($userId);
        if ($user->tokens < $pkg['price']) {
            return response()->json(['errorMessage' => 'Not enough tokens']);
        }

        // Flash deducts tokens and adds material_69 client-side when it receives {qty, price}
        $user->decrement('tokens', $pkg['price']);

        // Persist onigiri (material_69) in the character's inventory
        $existing = CharacterItem::where('character_id', $charId)
            ->where('item_id', 'material_69')
            ->first();
        if ($existing) {
            $existing->increment('quantity', $pkg['qty']);
        } else {
            CharacterItem::create([
                'character_id' => $charId,
                'item_id'      => 'material_69',
                'quantity'     => $pkg['qty'],
                'category'     => 'material',
            ]);
        }

        // Flash buyOnigiriResponse checks param1.hasOwnProperty("qty") then reads param1.price + param1.qty
        return response()->json(['qty' => $pkg['qty'], 'price' => $pkg['price']]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/member/{id}/onigiri/limit
    // -------------------------------------------------------------------------
    public function getOnigiriInfo(Request $request, $id): JsonResponse
    {
        return response()->json(['limit' => 10, 'used' => 0]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/member/{id}/onigiri/gift/{amount}
    // -------------------------------------------------------------------------
    public function giveOnigiri(Request $request, $id, $amount): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/invite-character/{id}
    // -------------------------------------------------------------------------
    public function inviteCharacter(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $actor  = ClanMember::where('character_id', $charId)->first();

        if (!$actor || !in_array($actor->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        // Target already in a clan
        if (ClanMember::where('character_id', $id)->exists()) {
            return response()->json(['errorMessage' => 'Player is already in a clan'], 422);
        }

        $clan = Clan::find($actor->clan_id);

        if (ClanMember::where('clan_id', $actor->clan_id)->count() >= $clan->max_members) {
            return response()->json(['errorMessage' => 'Clan is full'], 422);
        }

        // Send a clan invite mail so the target sees it in their mailbox with the Accept button.
        // The clan_id is embedded in the body so acceptInvitation can act on it.
        CharacterMail::create([
            'character_id' => $id,
            'type'         => 'clan_invite',
            'title'        => $clan->name . ' invites you to join!',
            'sender'       => $clan->name,
            'body'         => json_encode(['clan_id' => $actor->clan_id]),
            'rewards'      => null,
            'claimed'      => false,
            'viewed'       => false,
        ]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /clan/season-histories
    // -------------------------------------------------------------------------
    public function seasonHistories(Request $request): JsonResponse
    {
        $seasons = ClanSeason::orderByDesc('number')->limit(10)->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'number'     => $s->number,
                'started_at' => $s->started_at?->toIso8601String(),
                'ended_at'   => $s->ended_at?->toIso8601String(),
            ]);

        return response()->json(['seasons' => $seasons]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function activeSeason(): ClanSeason
    {
        $season = ClanSeason::where('active', true)->first();
        if (!$season) {
            $season = ClanSeason::create([
                'number'     => 1,
                'active'     => true,
                'started_at' => now(),
            ]);
        }
        return $season;
    }

    private function formatClan(Clan $clan, int $charId): array
    {
        $member    = $clan->members->firstWhere('character_id', $charId);
        $buildings = $clan->buildings ?? $clan->getDefaultBuildings();

        $elder      = $clan->members->firstWhere('role', 'elder');
        $masterChar = Character::find($clan->master_id);
        $elderChar  = $elder ? Character::find($elder->character_id) : null;

        return [
            'id'                   => $clan->id,
            'name'                 => $clan->name,
            'prestige'             => $clan->prestige,
            'reputation'           => $clan->prestige,
            'master_id'            => $clan->master_id,
            'master_name'          => $masterChar?->name,  // Flash: ClanHall generalMC.clan_master
            'elder_id'             => $elder?->character_id,
            'elder_name'           => $elderChar?->name,   // Flash: ClanHall generalMC.clan_elder
            'max_members'          => $clan->max_members,
            'members'              => $clan->members->count(),
            'golds'                => $clan->gold,
            'tokens'               => $clan->tokens,
            'announcement'         => $clan->announcement_published ?? '',
            'announcement_draft'   => $clan->announcement_draft ?? '',
            'role'                 => $member?->role ?? 'member',
            'stamina'              => $member?->stamina ?? 0,
            'max_stamina'          => $member?->max_stamina ?? 5,
            'season_id'            => $clan->season_id,
            'ramen'                => $buildings['ramen'] ?? 0,
            'hot_spring'           => $buildings['hot_spring'] ?? 0,
            'temple'               => $buildings['temple'] ?? 0,
            'training_hall'        => $buildings['training_hall'] ?? 0,
        ];
    }

    private function formatClanSummary(Clan $clan): array
    {
        return [
            'id'          => $clan->id,
            'name'        => $clan->name,
            'reputation'  => $clan->prestige,   // Flash: clans[i].reputation
            'max_members' => $clan->max_members,
            'members'     => $clan->members->count(),  // Flash: clans[i].members
        ];
    }

    private function prestigeBoostRemaining(ClanMember $member): int
    {
        if (!$member->prestige_boost_expires_at) {
            return 0;
        }
        return max(0, $member->prestige_boost_expires_at->timestamp - now()->timestamp);
    }

    private function formatMember(ClanMember $member): array
    {
        $character = $member->character ?? Character::find($member->character_id);

        return [
            // Fields read by Flash's ClanHall member list (displayMembers)
            'char_id'        => $member->character_id,  // Flash: members[i].char_id
            'name'           => $character?->name ?? 'Unknown',
            'level'          => $character?->level ?? 1,
            'stamina'        => $member->stamina,
            'max_stamina'    => $member->max_stamina,
            'reputation'     => 0,
            'gold_donated'   => $member->donated_golds,   // Flash: members[i].gold_donated
            'token_donated'  => $member->donated_tokens,  // Flash: members[i].token_donated
            'role'           => $member->role,
            // Fields read by Flash as char_data (ClanVillage / ClanPrestigeBooster)
            'prestige'       => 0,
            'prestige_boost' => $this->prestigeBoostRemaining($member),
        ];
    }
}