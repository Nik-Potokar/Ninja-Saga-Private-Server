<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterMail;
use App\Models\Crew;
use App\Models\CrewAuthToken;
use App\Models\CrewBattle;
use App\Models\CrewCastle;
use App\Models\CrewMember;
use App\Models\CrewRequest;
use App\Models\CrewSeason;
use App\Models\GameConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrewApiController extends Controller
{
    // Castle names from the game data (7 castles, indices 0-6)
    private const CASTLE_NAMES = [
        'Hiroshima Castle',
        'Himeji Castle',
        'Kumamoto Castle',
        'Okazaki Castle',
        'Inuyama Castle',
        'Gifu Castle',
        'Hikone Castle',
    ];

    // Building upgrade IDs sent by Flash → internal key mapping
    private const BUILDING_MAP = [
        'crew_kushi_dango'     => 'kushi_dango',
        'crew_tea_house'       => 'tea_house',
        'crew_bath_house'      => 'bath_house',
        'crew_training_centre' => 'training_centre',
    ];

    // Cost table per level (level 0→1 = price[1], etc.)
    private const BUILDING_PRICES = [
        1 => ['type' => 'gold',   'cost' => 1000000],
        2 => ['type' => 'gold',   'cost' => 1000000],
        3 => ['type' => 'tokens', 'cost' => 4000],
    ];

    private const CREATE_PRICE       = 1000;  // tokens
    private const MAX_STAMINA_CAP    = 200;
    private const STAMINA_UPGRADE_STEP = 50;
    private const STAMINA_UPGRADE_COST = 500; // tokens
    private const REFILL_STAMINA     = 50;
    private const REFILL_COST_TOKEN  = 10;
    private const REFILL_COST_ONIGIRI = 1;
    private const GOLDEN_ONIGIRI     = 'material_941';
    private const MAX_MEMBERS_CAP    = 40;
    private const MAX_MEMBERS_STEP   = 10;

    // -------------------------------------------------------------------------
    // POST /season
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
            'timestamp' => $countdown,          // Character.crew_timestamp
            'season'    => [                    // Character.crew_season
                'id'     => $season->id,
                'number' => $season->number,
            ],
            'phase' => $season->phase,          // Crew.instance.setPhase()
            'rp1'   => ['back_8001'],
            'rp2'   => ['back_8002', 'wpn_8001', 'skill_8001', 'wpn_8002',
                        'accessory_8001', 'back_8003', 'wpn_8003'],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /season/pool
    // -------------------------------------------------------------------------
    public function tokenPool(): JsonResponse
    {
        // Flash onGetTokenPool checks param1.hasOwnProperty("t")
        // then displays "Total Token Pool: " + param1.t + " + " + tokenPoolData.base
        return response()->json(['t' => 0]);
    }

    // -------------------------------------------------------------------------
    // POST /season/previous
    // -------------------------------------------------------------------------
    public function previousSeason(): JsonResponse
    {
        $charId = request()->attributes->get('char_id');
        // Return empty reward set; extend when season-end rewards are implemented
        return response()->json(['rewards' => [], 'season' => null]);
    }

    // -------------------------------------------------------------------------
    // POST /auth/login  { char_id, session_key }
    // -------------------------------------------------------------------------
    public function login(Request $request): JsonResponse
    {
        $charId     = $request->input('char_id');
        $sessionKey = trim((string) $request->input('session_key'));

        Log::info('Crew login attempt', [
            'char_id'             => $charId,
            'session_key_preview' => substr($sessionKey, 0, 8),
        ]);

        if (!$charId || !$sessionKey) {
            return response()->json(['status' => 0, 'error' => 'Missing credentials'], 422);
        }

        $character = Character::find($charId);
        if (!$character) {
            return response()->json(['status' => 0, 'error' => 'Invalid character'], 401);
        }

        $user = User::find($character->user_id);
        if (!$user) {
            return response()->json(['status' => 0, 'error' => 'Invalid session key'], 401);
        }

        if (!$this->sessionKeyMatches($user->id, (string) $user->session_key, $sessionKey)) {
            Log::warning('Crew login: session key mismatch', ['char_id' => $charId]);
            return response()->json(['status' => 0, 'error' => 'Invalid session key'], 401);
        }

        CrewAuthToken::where('character_id', $charId)->delete();
        $token = bin2hex(random_bytes(32));
        CrewAuthToken::create([
            'user_id'      => $user->id,
            'character_id' => $charId,
            'token'        => $token,
            'expires_at'   => now()->addHours(24),
        ]);

        Log::info('Crew login success', ['char_id' => $charId]);
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
    // POST /player/crew
    // -------------------------------------------------------------------------
    public function playerCrew(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            // Flash crewOnGetData checks param1.code == 404 → open CrewCreate
            return response()->json(['code' => 404]);
        }

        $crew = Crew::with('members')->find($member->crew_id);
        return response()->json([
            'crew' => $this->formatCrew($crew, $charId),
            'char' => $this->formatMember($member),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /player/stamina
    // -------------------------------------------------------------------------
    public function playerStamina(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['stamina' => 0, 'max_stamina' => 50]);
        }

        $season = $this->activeSeason();
        $now    = now()->timestamp;
        $secondsUntilReset = 86400 - ($now % 86400);

        // Flash onGetStamina reads param1.char.stamina / param1.season.timestamp / param1.season.phase
        return response()->json([
            'char'   => $this->formatMember($member),
            'season' => [
                'timestamp' => $secondsUntilReset,
                'phase'     => $season->phase,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /history
    // -------------------------------------------------------------------------
    public function history(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['histories' => []]);
        }

        $battles = CrewBattle::where('attacker_crew_id', $member->crew_id)
            ->orWhere('defender_crew_id', $member->crew_id)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($b) => [
                'id'               => $b->id,
                'attacker_crew_id' => $b->attacker_crew_id,
                'defender_crew_id' => $b->defender_crew_id,
                'attacker_won'     => $b->attacker_won,
                'created_at'       => $b->created_at?->toIso8601String(),
            ]);

        return response()->json(['histories' => $battles]);
    }

    // -------------------------------------------------------------------------
    // POST /player/crew/members
    // -------------------------------------------------------------------------
    public function crewMembers(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['members' => []]);
        }

        $members = CrewMember::with('character')
            ->where('crew_id', $member->crew_id)
            ->get()
            ->map(fn($m) => $this->formatMember($m));

        return response()->json(['members' => $members->values()]);
    }

    // -------------------------------------------------------------------------
    // POST /request/available
    // -------------------------------------------------------------------------
    public function availableCrews(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');

        $excludedCrewIds = CrewRequest::where('character_id', $charId)
            ->where('status', 'pending')
            ->pluck('crew_id');

        $inCrew = CrewMember::where('character_id', $charId)->value('crew_id');
        if ($inCrew) {
            $excludedCrewIds = $excludedCrewIds->push($inCrew);
        }

        $crews = Crew::with('members')
            ->whereNotIn('id', $excludedCrewIds)
            ->orderByDesc('prestige')
            ->limit(20)
            ->get()
            ->map(fn($c) => $this->formatCrewSummary($c));

        // Flash CrewCreate.onGetCrewsRes checks param1.hasOwnProperty("crews")
        return response()->json(['crews' => $crews]);
    }

    // -------------------------------------------------------------------------
    // POST /request/available/{id}
    // -------------------------------------------------------------------------
    public function searchAvailableCrew(Request $request, $id): JsonResponse
    {
        $crew = Crew::with('members')->find($id);
        if (!$crew) {
            return response()->json(['crews' => []]);
        }
        return response()->json(['crews' => [$this->formatCrewSummary($crew)]]);
    }

    // -------------------------------------------------------------------------
    // POST /player/request/{crewId}  — send join request
    // -------------------------------------------------------------------------
    public function sendRequest(Request $request, $crewId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');

        if (CrewMember::where('character_id', $charId)->exists()) {
            return response()->json(['errorMessage' => 'Already in a crew'], 422);
        }

        $crew = Crew::find($crewId);
        if (!$crew) {
            return response()->json(['errorMessage' => 'Crew not found'], 404);
        }

        $existing = CrewRequest::where('character_id', $charId)
            ->where('crew_id', $crewId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['errorMessage' => 'Request already sent'], 422);
        }

        CrewRequest::create([
            'crew_id'      => $crewId,
            'character_id' => $charId,
            'status'       => 'pending',
        ]);

        // Flash CrewCreate.onRequestToCrewRes checks param1.hasOwnProperty("data")
        return response()->json(['data' => ['result' => 'Request sent to ' . $crew->name . '!']]);
    }

    // -------------------------------------------------------------------------
    // POST /request/all
    // -------------------------------------------------------------------------
    public function memberRequests(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['requests' => []]);
        }

        $requests = CrewRequest::with('character')
            ->where('crew_id', $member->crew_id)
            ->where('status', 'pending')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'character_id'    => $r->character_id,
                'name'            => $r->character?->name ?? 'Unknown',
                'character_name'  => $r->character?->name ?? 'Unknown',
                'character_level' => $r->character?->level ?? 1,
                'created_at'      => $r->created_at?->toIso8601String(),
            ]);

        return response()->json(['requests' => $requests]);
    }

    // -------------------------------------------------------------------------
    // POST /request/all/reject
    // -------------------------------------------------------------------------
    public function rejectAllRequests(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        CrewRequest::where('crew_id', $member->crew_id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /request/{id}/reject
    // -------------------------------------------------------------------------
    public function rejectRequest(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        CrewRequest::where('id', $id)
            ->where('crew_id', $member->crew_id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /request/{id}/accept
    // -------------------------------------------------------------------------
    public function acceptRequest(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $joinRequest = CrewRequest::where('id', $id)
            ->where('crew_id', $member->crew_id)
            ->where('status', 'pending')
            ->first();

        if (!$joinRequest) {
            return response()->json(['error' => 'Request not found'], 404);
        }

        $crew         = Crew::find($member->crew_id);
        $currentCount = CrewMember::where('crew_id', $member->crew_id)->count();

        if ($currentCount >= $crew->max_members) {
            return response()->json(['error' => 'Crew is full'], 422);
        }

        DB::transaction(function () use ($joinRequest, $member) {
            CrewMember::where('character_id', $joinRequest->character_id)->delete();
            CrewMember::create([
                'crew_id'      => $member->crew_id,
                'character_id' => $joinRequest->character_id,
                'role'         => 'member',
                'stamina'      => 50,
                'max_stamina'  => 50,
            ]);
            $joinRequest->update(['status' => 'accepted']);
            CrewRequest::where('character_id', $joinRequest->character_id)
                ->where('id', '!=', $joinRequest->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);
        });

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /request/{id}/invite
    // -------------------------------------------------------------------------
    public function inviteCharacter(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $actor  = CrewMember::where('character_id', $charId)->first();

        if (!$actor || !in_array($actor->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (CrewMember::where('character_id', $id)->exists()) {
            return response()->json(['error' => 'Player is already in a crew'], 422);
        }

        $crew = Crew::find($actor->crew_id);

        if (CrewMember::where('crew_id', $actor->crew_id)->count() >= $crew->max_members) {
            return response()->json(['error' => 'Crew is full'], 422);
        }

        CharacterMail::create([
            'character_id' => $id,
            'type'         => 'crew_invite',
            'title'        => $crew->name . ' invites you to join!',
            'sender'       => $crew->name,
            'body'         => json_encode(['crew_id' => $actor->crew_id]),
            'rewards'      => null,
            'claimed'      => false,
            'viewed'       => false,
        ]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /player/quit
    // -------------------------------------------------------------------------
    public function quitCrew(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['error' => 'Not in a crew'], 422);
        }
        if ($member->role === 'master') {
            return response()->json(['error' => 'Master cannot quit. Transfer leadership first.'], 422);
        }

        $member->delete();
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /player/kick/{id}
    // -------------------------------------------------------------------------
    public function kickMember(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $actor  = CrewMember::where('character_id', $charId)->first();

        if (!$actor || !in_array($actor->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $target = CrewMember::where('crew_id', $actor->crew_id)
            ->where('character_id', $id)
            ->first();

        if (!$target) {
            return response()->json(['error' => 'Member not found'], 404);
        }
        if ($target->role === 'master') {
            return response()->json(['error' => 'Cannot kick the master'], 422);
        }
        if ($actor->role === 'elder' && $target->role === 'elder') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $target->delete();
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /player/promote-elder/{id}
    // -------------------------------------------------------------------------
    public function promoteElder(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $actor  = CrewMember::where('character_id', $charId)->first();

        if (!$actor || $actor->role !== 'master') {
            return response()->json(['error' => 'Only master can promote'], 403);
        }

        $target = CrewMember::where('crew_id', $actor->crew_id)
            ->where('character_id', $id)
            ->first();

        if (!$target) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        $newRole = $target->role === 'elder' ? 'member' : 'elder';
        $target->update(['role' => $newRole]);

        return response()->json(['success' => true, 'role' => $newRole]);
    }

    // -------------------------------------------------------------------------
    // POST /player/switch-master/{id}
    // -------------------------------------------------------------------------
    public function switchMaster(Request $request, $id): JsonResponse
    {
        $charId    = $request->attributes->get('char_id');
        $actor     = CrewMember::where('character_id', $charId)->first();

        if (!$actor || $actor->role !== 'master') {
            return response()->json(['error' => 'Only master can transfer leadership'], 403);
        }

        $newMaster = CrewMember::where('crew_id', $actor->crew_id)
            ->where('character_id', $id)
            ->first();

        if (!$newMaster) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        DB::transaction(function () use ($actor, $newMaster) {
            $actor->update(['role' => 'member']);
            $newMaster->update(['role' => 'master']);
            Crew::where('id', $actor->crew_id)->update(['master_id' => $newMaster->character_id]);
        });

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /player/donate/{amount}/golds
    // -------------------------------------------------------------------------
    public function donateGolds(Request $request, $amount): JsonResponse
    {
        $charId    = $request->attributes->get('char_id');
        $amount    = (int) $amount;
        $member    = CrewMember::where('character_id', $charId)->first();
        $character = Character::find($charId);

        if (!$member || !$character) {
            return response()->json(['error' => 'Not in a crew'], 422);
        }
        if ($character->gold < $amount) {
            return response()->json(['error' => 'Not enough gold'], 422);
        }

        DB::transaction(function () use ($character, $member, $amount) {
            $character->decrement('gold', $amount);
            $member->increment('donated_golds', $amount);
            Crew::where('id', $member->crew_id)->increment('gold', $amount);
        });

        return response()->json(['success' => true, 'gold' => $character->fresh()->gold]);
    }

    // -------------------------------------------------------------------------
    // POST /player/donate/{amount}/tokens
    // -------------------------------------------------------------------------
    public function donateTokens(Request $request, $amount): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $amount = (int) $amount;
        $member = CrewMember::where('character_id', $charId)->first();
        $user   = User::find($request->attributes->get('user_id'));

        if (!$member || !$user) {
            return response()->json(['error' => 'Not in a crew'], 422);
        }
        if ($user->tokens < $amount) {
            return response()->json(['error' => 'Not enough tokens'], 422);
        }

        DB::transaction(function () use ($user, $member, $amount) {
            $user->decrement('tokens', $amount);
            $member->increment('donated_tokens', $amount);
            Crew::where('id', $member->crew_id)->increment('tokens', $amount);
        });

        return response()->json(['success' => true, 'tokens' => $user->fresh()->tokens]);
    }

    // -------------------------------------------------------------------------
    // POST /upgrade/building/{id}
    // -------------------------------------------------------------------------
    public function upgradeBuilding(Request $request, $buildingId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['errorMessage' => 'Unauthorized'], 403);
        }

        // Flash sends e.g. "crew_kushi_dango", "crew_tea_house", etc.
        $buildingKey = self::BUILDING_MAP[$buildingId] ?? null;
        if (!$buildingKey) {
            return response()->json(['errorMessage' => 'Invalid building'], 422);
        }

        $crew      = Crew::find($member->crew_id);
        $buildings = $crew->buildings ?? $crew->getDefaultBuildings();
        $level     = $buildings[$buildingKey] ?? 0;

        if ($level >= 3) {
            return response()->json(['errorMessage' => 'Building is already at max level'], 422);
        }

        $priceInfo = self::BUILDING_PRICES[$level + 1];
        $cost      = $priceInfo['cost'];
        $costType  = $priceInfo['type'];

        if ($costType === 'gold') {
            if ($crew->gold < $cost) {
                return response()->json(['errorMessage' => 'Not enough crew gold']);
            }
            $buildings[$buildingKey] = $level + 1;
            $crew->update(['buildings' => $buildings, 'gold' => $crew->gold - $cost]);
        } else {
            if ($crew->tokens < $cost) {
                return response()->json(['errorMessage' => 'Not enough crew tokens']);
            }
            $buildings[$buildingKey] = $level + 1;
            $crew->update(['buildings' => $buildings, 'tokens' => $crew->tokens - $cost]);
        }

        $crew->refresh();

        // Return updated building levels + resource totals so Flash refreshes the UI immediately
        return response()->json([
            'success'         => true,
            'kushi_dango'     => $buildings['kushi_dango']     ?? 0,
            'tea_house'       => $buildings['tea_house']       ?? 0,
            'bath_house'      => $buildings['bath_house']      ?? 0,
            'training_centre' => $buildings['training_centre'] ?? 0,
            'golds'           => $crew->gold,
            'tokens'          => $crew->tokens,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /upgrade/max-members
    // -------------------------------------------------------------------------
    public function increaseMaxMembers(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || $member->role !== 'master') {
            return response()->json(['error' => 'Only master can increase member cap'], 403);
        }

        $crew = Crew::find($member->crew_id);

        if ($crew->max_members >= self::MAX_MEMBERS_CAP) {
            return response()->json(['error' => 'Already at max members'], 422);
        }

        // Cost in crew tokens: max_members * 500 (mirrors clan system)
        $cost = $crew->max_members * 500;

        if ($crew->tokens < $cost) {
            return response()->json(['error' => 'Not enough crew tokens'], 422);
        }

        $crew->update([
            'max_members' => min($crew->max_members + self::MAX_MEMBERS_STEP, self::MAX_MEMBERS_CAP),
            'tokens'      => $crew->tokens - $cost,
        ]);

        return response()->json(['success' => true, 'max_members' => $crew->max_members]);
    }

    // -------------------------------------------------------------------------
    // POST /announcements  { announcement }
    // -------------------------------------------------------------------------
    public function saveAnnouncement(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Crew::where('id', $member->crew_id)->update([
            'announcement_draft' => $request->input('announcement', ''),
        ]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /announcement/publish
    // -------------------------------------------------------------------------
    public function publishAnnouncement(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || !in_array($member->role, ['master', 'elder'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $crew = Crew::find($member->crew_id);
        $crew->update(['announcement_published' => $crew->announcement_draft]);

        // Flash onPublishedAnnouncement checks param1 == "ok"
        return response()->json("ok");
    }

    // -------------------------------------------------------------------------
    // POST /player/stamina/upgrade-max
    // -------------------------------------------------------------------------
    public function upgradeMaxStamina(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $member = CrewMember::where('character_id', $charId)->first();
        $user   = User::find($userId);

        if (!$member || !$user) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }

        if ($member->max_stamina >= self::MAX_STAMINA_CAP) {
            return response()->json(['errorMessage' => 'Max stamina already at cap']);
        }

        if ($user->tokens < self::STAMINA_UPGRADE_COST) {
            return response()->json(['errorMessage' => 'Not enough tokens']);
        }

        $newMax = min($member->max_stamina + self::STAMINA_UPGRADE_STEP, self::MAX_STAMINA_CAP);
        $user->decrement('tokens', self::STAMINA_UPGRADE_COST);
        $member->update(['max_stamina' => $newMax]);

        // Flash onUpgradeStaminaRes checks param1.status == "ok"
        return response()->json(['status' => 'ok']);
    }

    // -------------------------------------------------------------------------
    // POST /player/boost-prestige
    // -------------------------------------------------------------------------
    public function boostPrestige(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $member = CrewMember::where('character_id', $charId)->first();
        $user   = User::find($userId);

        if (!$member || !$user) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }

        $cost = 100;
        if ($user->tokens < $cost) {
            return response()->json(['errorMessage' => 'Not enough tokens']);
        }

        $boostSeconds = 86400;
        $expiresAt    = now()->addSeconds($boostSeconds);

        DB::transaction(function () use ($user, $member, $cost, $expiresAt) {
            $user->decrement('tokens', $cost);
            $member->update(['prestige_boost_expires_at' => $expiresAt]);
        });

        return response()->json([
            'tokens'         => $user->fresh()->tokens,
            'prestige_boost' => $boostSeconds,
            'result'         => 'Prestige Boost activated for 24 hours!',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /player/stamina/refill
    // -------------------------------------------------------------------------
    public function refillStamina(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }

        if ($member->stamina >= $member->max_stamina) {
            return response()->json(['errorMessage' => 'Stamina is already full']);
        }

        $restored = min(self::REFILL_STAMINA, $member->max_stamina - $member->stamina);

        // Try Golden Onigiri first, then fall back to tokens
        $onigiriItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', self::GOLDEN_ONIGIRI)
            ->first();

        if ($onigiriItem && $onigiriItem->quantity >= self::REFILL_COST_ONIGIRI) {
            // Use Golden Onigiri
            $onigiriItem->decrement('quantity', self::REFILL_COST_ONIGIRI);
            $member->increment('stamina', $restored);

            // Flash checks param1.status == "ok" && param1.data.currency_type == 2
            return response()->json([
                'status' => 'ok',
                'data'   => [
                    'currency_type'      => 2,
                    'currency_remaining' => $onigiriItem->fresh()->quantity,
                    'currency_used'      => self::REFILL_COST_ONIGIRI,
                    'restored_stamina'   => $restored,
                ],
            ]);
        }

        // Fall back to tokens
        $user = User::find($userId);
        if (!$user || $user->tokens < self::REFILL_COST_TOKEN) {
            return response()->json(['errorMessage' => 'Not enough tokens or Golden Stamina Roll']);
        }

        $user->decrement('tokens', self::REFILL_COST_TOKEN);
        $member->increment('stamina', $restored);

        // Flash checks param1.data.currency_type == 1
        return response()->json([
            'status' => 'ok',
            'data'   => [
                'currency_type'      => 1,
                'currency_remaining' => $user->fresh()->tokens,
                'currency_used'      => self::REFILL_COST_TOKEN,
                'restored_stamina'   => $restored,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /player/buy-onigiri/{id}
    // -------------------------------------------------------------------------
    public function buyOnigiriPackage(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');

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

        $user->decrement('tokens', $pkg['price']);

        $existing = CharacterItem::where('character_id', $charId)
            ->where('item_id', self::GOLDEN_ONIGIRI)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $pkg['qty']);
        } else {
            CharacterItem::create([
                'character_id' => $charId,
                'item_id'      => self::GOLDEN_ONIGIRI,
                'quantity'     => $pkg['qty'],
                'category'     => 'material',
            ]);
        }

        return response()->json(['qty' => $pkg['qty'], 'price' => $pkg['price']]);
    }

    // -------------------------------------------------------------------------
    // POST /member/{id}/onigiri/limit
    // -------------------------------------------------------------------------
    public function getOnigiriInfo(Request $request, $id): JsonResponse
    {
        return response()->json(['limit' => 10, 'used' => 0]);
    }

    // -------------------------------------------------------------------------
    // POST /member/{id}/onigiri/gift/{amount}
    // -------------------------------------------------------------------------
    public function giveOnigiri(Request $request, $id, $amount): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /create  { name }
    // -------------------------------------------------------------------------
    public function createCrew(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $name   = trim($request->input('name', ''));

        if (strlen($name) < 3 || strlen($name) > 50) {
            return response()->json(['errorMessage' => 'Name must be 3-50 characters'], 422);
        }
        if (CrewMember::where('character_id', $charId)->exists()) {
            return response()->json(['errorMessage' => 'Already in a crew'], 422);
        }
        if (Crew::where('name', $name)->exists()) {
            return response()->json(['errorMessage' => 'Crew name already taken'], 422);
        }

        $user = User::find($userId);
        if (!$user || $user->tokens < self::CREATE_PRICE) {
            return response()->json(['errorMessage' => 'Not enough tokens'], 422);
        }

        DB::transaction(function () use ($charId, $userId, $name, $user) {
            $season = $this->activeSeason();
            $crew   = Crew::create([
                'season_id' => $season->id,
                'name'      => $name,
                'master_id' => $charId,
                'buildings' => (new Crew)->getDefaultBuildings(),
            ]);
            CrewMember::create([
                'crew_id'      => $crew->id,
                'character_id' => $charId,
                'role'         => 'master',
                'stamina'      => 50,
                'max_stamina'  => 50,
            ]);
            $user->decrement('tokens', self::CREATE_PRICE);
        });

        // Flash onCreateCrewRes checks param1.status == "ok"
        return response()->json(['status' => 'ok']);
    }

    // -------------------------------------------------------------------------
    // POST /rename  { name }
    // -------------------------------------------------------------------------
    public function renameCrew(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $name   = trim($request->input('name', ''));
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member || $member->role !== 'master') {
            return response()->json(['error' => 'Only master can rename crew'], 403);
        }
        if (strlen($name) < 3 || strlen($name) > 50) {
            return response()->json(['error' => 'Name must be 3-50 characters'], 422);
        }
        if (Crew::where('name', $name)->where('id', '!=', $member->crew_id)->exists()) {
            return response()->json(['error' => 'Crew name already taken'], 422);
        }

        Crew::where('id', $member->crew_id)->update(['name' => $name]);
        // Flash onCrewRenameRes checks param1 == "ok"
        return response()->json("ok");
    }

    // -------------------------------------------------------------------------
    // POST /season-histories
    // -------------------------------------------------------------------------
    public function seasonHistories(Request $request): JsonResponse
    {
        $seasons = CrewSeason::orderByDesc('number')->limit(10)->get()
            ->map(fn($s) => [
                'id'         => $s->id,
                'number'     => $s->number,
                'started_at' => $s->started_at?->toIso8601String(),
                'ended_at'   => $s->ended_at?->toIso8601String(),
            ]);

        return response()->json(['seasons' => $seasons]);
    }

    // =========================================================================
    // Battle: Opponent search (for Crew vs Crew battles — future feature)
    // =========================================================================

    // -------------------------------------------------------------------------
    // POST /battle/opponents
    // -------------------------------------------------------------------------
    public function battleOpponents(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['crews' => []]);
        }

        $crews = Crew::with('members')
            ->where('id', '!=', $member->crew_id)
            ->orderByDesc('prestige')
            ->limit(10)
            ->get()
            ->map(fn($c) => $this->formatCrewSummary($c));

        return response()->json(['crews' => $crews]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/opponents/{id}
    // -------------------------------------------------------------------------
    public function searchBattleOpponent(Request $request, $id): JsonResponse
    {
        $crew = Crew::with('members')->find($id);
        if (!$crew) {
            return response()->json(['crews' => []]);
        }
        return response()->json(['crews' => [$this->formatCrewSummary($crew)]]);
    }

    // =========================================================================
    // Battle: Castle system
    // =========================================================================

    // -------------------------------------------------------------------------
    // POST /battle/castles/   (no ID)
    // -------------------------------------------------------------------------
    public function getCastles(Request $request): JsonResponse
    {
        $season  = $this->activeSeason();
        $castles = $this->ensureCastlesExist($season);

        $castleObj = [];
        foreach ($castles as $castle) {
            $ownerCrew = $castle->owner_crew_id ? Crew::find($castle->owner_crew_id) : null;
            $castleObj[$castle->castle_index] = [
                'id'         => $castle->id,
                'name'       => self::CASTLE_NAMES[$castle->castle_index] ?? $castle->name,
                'owner_id'   => $castle->owner_crew_id,
                'owner_name' => $ownerCrew?->name ?? '',
                'wall_hp'    => $castle->wall_hp,
                'defender_hp'=> $castle->defender_hp,
            ];
        }

        // Flash onCastleData checks param1.hasOwnProperty("castles")
        // and onCastleData reads param1.a for notification text
        return response()->json(['castles' => $castleObj, 'a' => '']);
    }

    // -------------------------------------------------------------------------
    // POST /battle/castles/{id}  (specific castle by DB id)
    // -------------------------------------------------------------------------
    public function getCastle(Request $request, $id): JsonResponse
    {
        $castle    = CrewCastle::find($id);
        if (!$castle) {
            return response()->json(['castles' => []]);
        }

        $ownerCrew = $castle->owner_crew_id ? Crew::find($castle->owner_crew_id) : null;

        // Flash onRefreshCastle checks param1.castles.length > 0 (array)
        return response()->json([
            'castles' => [[
                'id'          => $castle->id,
                'name'        => self::CASTLE_NAMES[$castle->castle_index] ?? $castle->name,
                'owner_id'    => $castle->owner_crew_id,
                'owner_name'  => $ownerCrew?->name ?? '',
                'wall_hp'     => $castle->wall_hp,
                'defender_hp' => $castle->defender_hp,
            ]],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/castles/{id}/ranks
    // -------------------------------------------------------------------------
    public function castleRanks(Request $request, $id): JsonResponse
    {
        // Return empty ranking for now; extend when scoring is implemented
        return response()->json(['ranks' => []]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/castles/{id}/recovery  { t: timestamp }
    // -------------------------------------------------------------------------
    public function castleRecovery(Request $request, $id): JsonResponse
    {
        $charId  = $request->attributes->get('char_id');
        $userId  = $request->attributes->get('user_id');
        $user    = User::find($userId);
        $castle  = CrewCastle::find($id);

        if (!$castle) {
            return response()->json(['errorMessage' => 'Castle not found'], 404);
        }

        // Recovery is available every 30 minutes
        $nextRecovery = 0;
        if ($castle->last_recovery_at) {
            $nextRecovery = max(0, $castle->last_recovery_at->timestamp + 1800 - now()->timestamp);
        }

        // Flash onRecoveryData reads: n (next ts), t (tokens), a (amount), f (flag)
        return response()->json([
            'n' => now()->timestamp + $nextRecovery,
            't' => $user?->tokens ?? 0,
            'a' => 5,   // HP percentage to recover per 30 min
            'f' => '',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/castles/{id}/recover
    // -------------------------------------------------------------------------
    public function castleRecover(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $member = CrewMember::where('character_id', $charId)->first();
        $castle = CrewCastle::find($id);
        $user   = User::find($userId);

        if (!$castle || !$member) {
            return response()->json(['errorMessage' => 'Castle not found'], 404);
        }

        // Only the crew that owns the castle can recover it
        if ((int)$castle->owner_crew_id !== (int)$member->crew_id) {
            return response()->json(['errorMessage' => 'You do not own this castle'], 403);
        }

        // Check 30-minute cooldown
        if ($castle->last_recovery_at && now()->diffInMinutes($castle->last_recovery_at) < 30) {
            $remaining = 30 - now()->diffInMinutes($castle->last_recovery_at);
            return response()->json([
                'errorMessage' => "Recovery available in {$remaining} minutes",
                'code'         => 429,
            ]);
        }

        $recover = 5; // HP percentage
        $castle->update([
            'wall_hp'           => min(100, $castle->wall_hp + $recover),
            'last_recovery_at'  => now(),
        ]);

        return response()->json([
            'n' => now()->timestamp + 1800,
            't' => $user?->tokens ?? 0,
            'a' => $recover,
            'f' => '',
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/castles/{id}/defenders
    // -------------------------------------------------------------------------
    public function castleDefenders(Request $request, $id): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();
        $castle = CrewCastle::find($id);

        if (!$castle) {
            return response()->json(['defenders' => []]);
        }

        // If not owned by anyone or owned by another crew, return empty
        if (!$castle->owner_crew_id) {
            return response()->json(['defenders' => []]);
        }

        $defenders = CrewMember::with('character')
            ->where('crew_id', $castle->owner_crew_id)
            ->where('battle_role', 2)
            ->get()
            ->map(fn($m) => $this->formatMember($m));

        return response()->json(['defenders' => $defenders]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/role/switch/{castleId}
    // -------------------------------------------------------------------------
    public function switchRole(Request $request, $castleId): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }

        // Toggle battle_role between 1 (attacker) and 2 (defender)
        $newRole = $member->battle_role == 1 ? 2 : 1;
        $limitAt = $newRole == 2 ? now()->addHours(24)->format('Y-m-d H:i:s') : '';

        $member->update([
            'battle_role'   => $newRole,
            'role_limit_at' => $limitAt,
        ]);

        return response()->json(['success' => true, 'role' => $newRole]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/attackers
    // -------------------------------------------------------------------------
    public function battleAttackers(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['members' => []]);
        }

        $attackers = CrewMember::with('character')
            ->where('crew_id', $member->crew_id)
            ->where('battle_role', 1)
            ->get()
            ->map(fn($m) => $this->formatMember($m));

        return response()->json(['members' => $attackers->values()]);
    }

    // =========================================================================
    // Battle: Phase 1 (Boss / PvE)
    // =========================================================================

    // -------------------------------------------------------------------------
    // POST /battle/phase1/start
    // -------------------------------------------------------------------------
    public function startPhaseOneBattle(Request $request): JsonResponse
    {
        $charId    = $request->attributes->get('char_id');
        $member    = CrewMember::where('character_id', $charId)->first();
        $castleDbId = (int) $request->input('c');

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }
        if ($member->stamina < 10) {
            return response()->json(['errorMessage' => 'Not enough stamina', 'code' => 402]);
        }

        $season = $this->activeSeason();
        $this->ensureCastlesExist($season);

        // Flash may send the DB id or the castle_index (0-6) — support both
        $castle = CrewCastle::find($castleDbId)
            ?? CrewCastle::where('castle_index', $castleDbId)
                          ->where('season_id', $season->id)
                          ->first();
        if (!$castle) {
            return response()->json(['errorMessage' => 'Castle not found']);
        }

        // Flash startBattleResponse checks param1.hasOwnProperty("c")
        // then starts BattleManager with the boss enemies from GameData (client-side)
        return response()->json([
            'c' => $castleDbId,
            'f' => [],  // friend recruits (empty for now)
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/phase1/finish
    // -------------------------------------------------------------------------
    public function finishPhaseOneBattle(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    // =========================================================================
    // Battle: Phase 2 (Castle capture)
    // =========================================================================

    // -------------------------------------------------------------------------
    // POST /battle/phase2/start
    // -------------------------------------------------------------------------
    public function startPhaseTwoBattle(Request $request): JsonResponse
    {
        $charId    = $request->attributes->get('char_id');
        $member    = CrewMember::where('character_id', $charId)->first();
        $castleDbId = (int) $request->input('c');

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a crew', 'code' => 400]);
        }
        if ($member->stamina < 10) {
            return response()->json(['errorMessage' => 'Not enough stamina', 'code' => 402]);
        }

        $season = $this->activeSeason();
        $this->ensureCastlesExist($season);

        // Flash may send the DB id or the castle_index (0-6) — support both
        $castle = CrewCastle::find($castleDbId)
            ?? CrewCastle::where('castle_index', $castleDbId)
                          ->where('season_id', $season->id)
                          ->first();
        if (!$castle) {
            return response()->json(['errorMessage' => 'Castle not found', 'code' => 404]);
        }

        $attackerCrew = Crew::find($member->crew_id);

        // If attacker's crew already owns this castle
        if ((int)$castle->owner_crew_id === (int)$member->crew_id) {
            return response()->json([
                'errorMessage' => 'The castle has been taken over by your crew.',
                'code'         => 406,
            ]);
        }

        $defenderCrew = $castle->owner_crew_id ? Crew::find($castle->owner_crew_id) : null;

        // Resolve battle: attack reduces wall_hp by a random amount
        $damage    = rand(5, 20);
        $won       = ($castle->wall_hp - $damage) <= 0;
        $merit     = $won ? rand(50, 150) : rand(10, 50);

        $cfg              = GameConfig::get('prestige_settings', []);
        $crewWinPrestige  = (int) ($cfg['crew_win_prestige']  ?? 10);
        $crewLosePrestige = (int) ($cfg['crew_lose_prestige'] ?? 5);

        DB::transaction(function () use ($member, $castle, $attackerCrew, $defenderCrew, $damage, $won, $crewWinPrestige, $crewLosePrestige) {
            $member->decrement('stamina', 10);

            $newWallHp = max(0, $castle->wall_hp - $damage);

            if ($won) {
                // Attacker takes the castle
                $castle->update([
                    'owner_crew_id' => $attackerCrew->id,
                    'wall_hp'       => 100,
                    'defender_hp'   => 100,
                ]);
                $attackerCrew->increment('prestige', $crewWinPrestige);
                if ($defenderCrew) {
                    $defenderCrew->update(['prestige' => max(0, $defenderCrew->prestige - $crewLosePrestige)]);
                }
            } else {
                $castle->update(['wall_hp' => $newWallHp]);
            }

            CrewBattle::create([
                'season_id'       => $this->activeSeason()->id,
                'castle_id'       => $castle->id,
                'attacker_crew_id'=> $attackerCrew->id,
                'defender_crew_id'=> $defenderCrew?->id,
                'attacker_won'    => $won,
            ]);
        });

        $castle->refresh();
        $member->refresh();

        // Flash onBattleResult checks param1.hasOwnProperty("b")
        // then reads: w (winner name), l (loser name), d (damage), m (merit), s (stamina)
        return response()->json([
            'b' => true,
            'w' => $won ? $attackerCrew->name : ($defenderCrew?->name ?? 'Neutral'),
            'l' => $won ? ($defenderCrew?->name ?? 'Neutral') : $attackerCrew->name,
            'd' => $damage,
            'm' => $merit,
            's' => $member->stamina,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /battle/phase2/finish
    // -------------------------------------------------------------------------
    public function finishPhaseTwoBattle(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    // =========================================================================
    // Mini-game
    // =========================================================================

    // -------------------------------------------------------------------------
    // POST /player/minigame
    // -------------------------------------------------------------------------
    public function miniGame(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['energy' => 0]);
        }

        // Flash onMiniGameRes checks param1.hasOwnProperty("energy")
        return response()->json(['energy' => $member->minigame_energy]);
    }

    // -------------------------------------------------------------------------
    // POST /player/minigame/start
    // -------------------------------------------------------------------------
    public function startMiniGame(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }
        if ($member->minigame_energy <= 0) {
            return response()->json(['errorMessage' => 'No energy left']);
        }

        $sessionCode = bin2hex(random_bytes(16));
        $timestamp   = now()->timestamp;

        // Flash onStartRes checks param1.hasOwnProperty("c")
        return response()->json([
            'c' => $sessionCode,
            't' => $timestamp,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /player/minigame/finish  { c, t, r, n, h }
    // -------------------------------------------------------------------------
    public function finishMiniGame(Request $request): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $member = CrewMember::where('character_id', $charId)->first();
        $result = (int) $request->input('r', 0); // 1 = won all stages

        if (!$member) {
            return response()->json(['errorMessage' => 'Not in a crew']);
        }

        if ($member->minigame_energy > 0) {
            $member->decrement('minigame_energy');
        }

        if ($result !== 1) {
            // Player lost — no reward, show lose popup
            return response()->json(['errorMessage' => 'Game not completed']);
        }

        // Give reward: a random material from the minigame rewards list
        $rewardItems  = ['material_939', 'material_941'];
        $rewardItemId = $rewardItems[array_rand($rewardItems)];
        $qty          = rand(1, 3);

        $existing = CharacterItem::where('character_id', $charId)
            ->where('item_id', $rewardItemId)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $qty);
        } else {
            CharacterItem::create([
                'character_id' => $charId,
                'item_id'      => $rewardItemId,
                'quantity'     => $qty,
                'category'     => 'material',
            ]);
        }

        // Flash finishMiniGameRes checks param1.hasOwnProperty("r") then reads param1.r as rewards
        return response()->json([
            'r' => [['id' => $rewardItemId, 'amount' => $qty]],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /player/minigame/buy/{type}
    //   type 1 = buy extra time   type 2 = buy extra heart
    // -------------------------------------------------------------------------
    public function buyMiniGame(Request $request, $type): JsonResponse
    {
        $charId = $request->attributes->get('char_id');
        $userId = $request->attributes->get('user_id');
        $user   = User::find($userId);
        $cost   = 5; // tokens per purchase

        if (!$user || $user->tokens < $cost) {
            return response()->json(['errorMessage' => 'Not enough tokens']);
        }

        $user->decrement('tokens', $cost);

        // Flash buyTimeResponse / buyHeartResponse checks param1.hasOwnProperty("t")
        return response()->json(['t' => $user->fresh()->tokens]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function activeSeason(): CrewSeason
    {
        $season = CrewSeason::where('active', true)->first();
        if (!$season) {
            $season = CrewSeason::create([
                'number'     => 1,
                'active'     => true,
                'phase'      => 1,
                'started_at' => now(),
            ]);
        }
        return $season;
    }

    /**
     * Ensure all 7 castle records exist for the given season.
     */
    private function ensureCastlesExist(CrewSeason $season): \Illuminate\Support\Collection
    {
        $existing = CrewCastle::where('season_id', $season->id)
            ->orderBy('castle_index')
            ->get();

        if ($existing->count() < 7) {
            for ($i = 0; $i < 7; $i++) {
                CrewCastle::firstOrCreate(
                    ['season_id' => $season->id, 'castle_index' => $i],
                    [
                        'name'       => self::CASTLE_NAMES[$i],
                        'wall_hp'    => 100,
                        'defender_hp'=> 100,
                    ]
                );
            }
            $existing = CrewCastle::where('season_id', $season->id)
                ->orderBy('castle_index')
                ->get();
        }

        return $existing;
    }

    private function formatCrew(Crew $crew, int $charId): array
    {
        $member    = $crew->members->firstWhere('character_id', $charId);
        $buildings = $crew->buildings ?? $crew->getDefaultBuildings();

        $elder      = $crew->members->firstWhere('role', 'elder');
        $masterChar = Character::find($crew->master_id);
        $elderChar  = $elder ? Character::find($elder->character_id) : null;

        return [
            'id'                   => $crew->id,
            'name'                 => $crew->name,
            'prestige'             => $crew->prestige,
            'master_id'            => $crew->master_id,
            'master_name'          => $masterChar?->name ?? '',
            'elder_id'             => $elder?->character_id ?? 0,
            'elder_name'           => $elderChar?->name ?? '',
            'max_members'          => $crew->max_members,
            'members'              => $crew->members->count(),
            'golds'                => $crew->gold,
            'tokens'               => $crew->tokens,
            'announcement'         => $crew->announcement_published ?? '',
            'announcement_draft'   => $crew->announcement_draft ?? '',
            // Building levels — Flash reads crewData.kushi_dango etc.
            'kushi_dango'          => $buildings['kushi_dango']     ?? 0,
            'tea_house'            => $buildings['tea_house']       ?? 0,
            'bath_house'           => $buildings['bath_house']      ?? 0,
            'training_centre'      => $buildings['training_centre'] ?? 0,
        ];
    }

    private function formatCrewSummary(Crew $crew): array
    {
        return [
            'id'          => $crew->id,
            'name'        => $crew->name,
            'prestige'    => $crew->prestige,
            'max_members' => $crew->max_members,
            'members'     => $crew->members->count(),
        ];
    }

    private function formatMember(CrewMember $member): array
    {
        $character = $member->character ?? Character::find($member->character_id);

        return [
            'char_id'        => $member->character_id,
            'name'           => $character?->name ?? 'Unknown',
            'level'          => $character?->level ?? 1,
            'stamina'        => $member->stamina,
            'max_stamina'    => $member->max_stamina,
            'role'           => $member->role,
            'battle_role'    => $member->battle_role,
            'role_limit_at'  => $member->role_limit_at ?? '',
            'donated_golds'  => $member->donated_golds,
            'donated_tokens' => $member->donated_tokens,
            'prestige_boost' => $this->prestigeBoostRemaining($member),
        ];
    }

    private function prestigeBoostRemaining(CrewMember $member): int
    {
        if (!$member->prestige_boost_expires_at) {
            return 0;
        }
        return max(0, $member->prestige_boost_expires_at->timestamp - now()->timestamp);
    }
}