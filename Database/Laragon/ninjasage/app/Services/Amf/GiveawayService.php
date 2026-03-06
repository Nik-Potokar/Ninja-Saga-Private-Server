<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\Giveaway;
use App\Models\GiveawayParticipant;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

/**
 * GiveawayService AMF Service — backs the GiveawayCenter panel.
 *
 * Client calls:
 *   GiveawayService.get(charId, sessionKey)
 *   GiveawayService.history(charId, sessionKey)
 *   GiveawayService.participate(charId, sessionKey, giveawayId)
 *
 * Giveaway requirements (stored as JSON on the row):
 *   [{"name": "Level 30+", "type": "level",       "value": 30},
 *    {"name": "50 PvP Battles", "type": "pvp_battles", "value": 50},
 *    {"name": "20 PvP Wins",    "type": "pvp_wins",    "value": 20},
 *    {"name": "Chunin Rank",    "type": "rank",         "value": 2}]
 */
class GiveawayService
{
    use ValidatesSession;

    // How many days of ended giveaways to include in the get response (for winner display)
    private const ENDED_DAYS = 30;

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey
     * Returns: { status: 1, giveaways: [...] }
     *
     * Returns active giveaways + recently ended ones so players can see who won.
     */
    public function get($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $charId = (int) $charId;
        $char   = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $cutoff = now()->subDays(self::ENDED_DAYS);

        // Active giveaways + recently ended ones with winners
        $giveaways = Giveaway::where(function ($q) use ($cutoff) {
            $q->where('ends_at', '>', now())             // still active
              ->orWhere(function ($q2) use ($cutoff) {   // ended recently and has winners set
                  $q2->where('ends_at', '<=', now())
                     ->where('ends_at', '>=', $cutoff)
                     ->whereNotNull('winners');
              });
        })
        ->orderByRaw("ends_at > NOW() DESC")  // active first
        ->orderBy('ends_at', 'asc')
        ->get();

        $participantCounts = $giveaways->mapWithKeys(fn($g) =>
            [$g->id => $g->participants()->count()]
        );

        $joinedIds = GiveawayParticipant::where('character_id', $charId)
            ->whereIn('giveaway_id', $giveaways->pluck('id'))
            ->pluck('giveaway_id')
            ->flip();

        $result = $giveaways->map(function (Giveaway $g) use ($char, $participantCounts, $joinedIds) {
            $active  = $g->isActive();
            $winners = $g->winners;

            return [
                'id'           => $g->id,
                'title'        => $g->title,
                'desc'         => $g->description,
                'prizes'       => $g->prizes ?? [],
                'participants' => $participantCounts[$g->id] ?? 0,
                'timestamp'    => $active ? max(0, (int) now()->diffInSeconds($g->ends_at)) : 0,
                'joined'       => isset($joinedIds[$g->id]) ? 1 : 0,
                'requirements' => $this->buildRequirementsProgress($g->requirements ?? [], $char),
                'winners'      => ($winners && count($winners) > 0) ? array_values($winners) : null,
            ];
        })->values()->all();

        return [
            'status'    => 1,
            'giveaways' => $result,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey
     * Returns: { status: 1, giveaway: [...] }  (note: singular key, array value)
     *
     * Returns all ended giveaways for the history panel.
     */
    public function history($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $ended = Giveaway::where('ends_at', '<=', now())
            ->orderBy('ends_at', 'desc')
            ->get();

        $history = $ended->map(fn(Giveaway $g) => [
            'title'       => $g->title,
            'description' => $g->description,
            'ended_at'    => $g->ends_at->format('Y-m-d H:i'),
            'prizes'      => $g->prizes ?? [],
        ])->values()->all();

        return [
            'status'   => 1,
            'giveaway' => $history,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, giveawayId
     * Returns: { status: 1 }
     */
    public function participate($charId, $sessionKey, $giveawayId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        $charId     = (int) $charId;
        $giveawayId = (int) $giveawayId;

        $giveaway = Giveaway::find($giveawayId);
        if (!$giveaway) {
            return ['status' => 2, 'result' => 'Giveaway not found.'];
        }

        if (!$giveaway->isActive()) {
            return ['status' => 2, 'result' => 'This giveaway has already ended.'];
        }

        if ($giveaway->hasWinners()) {
            return ['status' => 2, 'result' => 'This giveaway has already ended.'];
        }

        // Check if already joined
        $alreadyJoined = GiveawayParticipant::where('giveaway_id', $giveawayId)
            ->where('character_id', $charId)
            ->exists();

        if ($alreadyJoined) {
            return ['status' => 2, 'result' => 'You have already joined this giveaway.'];
        }

        // Validate requirements
        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        foreach ($giveaway->requirements ?? [] as $req) {
            $type     = $req['type'] ?? '';
            $value    = (int) ($req['value'] ?? 0);
            $progress = $this->getRequirementProgress($char, $type, $value);
            if ($progress < $value) {
                $name = $req['name'] ?? $type;
                return ['status' => 2, 'result' => "Requirement not met: {$name} ({$progress}/{$value})."];
            }
        }

        GiveawayParticipant::create([
            'giveaway_id'  => $giveawayId,
            'character_id' => $charId,
        ]);

        Log::info("GiveawayService.participate: Char {$charId} joined Giveaway {$giveawayId}");

        return ['status' => 1, 'error' => 0];
    }

    // -------------------------------------------------------------------------

    private function buildRequirementsProgress(array $requirements, Character $char): array
    {
        $result = [];
        foreach ($requirements as $req) {
            $type    = $req['type']  ?? '';
            $value   = (int) ($req['value'] ?? 0);
            $progress = $this->getRequirementProgress($char, $type, $value);
            $result[] = [
                'name'     => $req['name'] ?? $type,
                'progress' => min($progress, $value),
                'total'    => $value,
            ];
        }
        return $result;
    }

    private function getRequirementProgress(Character $char, string $type, int $value): int
    {
        return match ($type) {
            'level'       => (int) $char->level,
            'pvp_battles' => (int) $char->pvp_played,
            'pvp_wins'    => (int) $char->pvp_won,
            'rank'        => (int) $char->rank,
            default       => $value, // unknown type → auto-pass
        };
    }
}
