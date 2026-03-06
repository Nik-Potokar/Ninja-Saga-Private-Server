<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

/**
 * JusticeBadgeEvent2024 AMF Service
 *
 * Admin GameEvent config (data JSON, panel = "JusticeBadge"):
 * {
 *   "end": "Mar 31, 2026",
 *   "rewards": [
 *     { "id": "xp_percent_100", "requirement": 5  },
 *     { "id": "gold_50000",     "requirement": 10 },
 *     { "id": "tp_100",         "requirement": 15 },
 *     { "id": "ss_50",          "requirement": 20 }
 *   ]
 * }
 *
 * The client reads reward display names from client-side GameData (justice_badge.rewards).
 * It sends only the `requirement` (badge count) when exchanging; the server matches
 * that to its own rewards list to determine what to grant.
 */
class JusticeBadgeEvent2024Service
{
    use ValidatesSession;

    private const PANEL    = 'JusticeBadge';
    private const MATERIAL = 'material_2110'; // Justice Badge item

    // -------------------------------------------------------------------------

    public function getEventData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF JusticeBadgeEvent2024.getEventData: Char $charId");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Justice Badge event is currently inactive.'];
        }

        $char = Character::find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $badges = $this->badgeCount($char->id);

        return [
            'status'    => 1,
            'error'     => 0,
            'materials' => $badges,
            'end'       => $config['end'] ?? '',
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, requirement (badge cost sent by client)
     */
    public function exchange($charId, $sessionKey, $requirement)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF JusticeBadgeEvent2024.exchange: Char $charId Req $requirement");

        $config = $this->config();
        if (!$config) {
            return ['status' => 2, 'result' => 'Justice Badge event is currently inactive.'];
        }

        $requirement = (int) $requirement;
        $char        = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Find matching reward in config.
        $rewardsConfig = $config['rewards'] ?? [];
        $rewardEntry   = null;
        foreach ($rewardsConfig as $entry) {
            if ((int) ($entry['requirement'] ?? 0) === $requirement) {
                $rewardEntry = $entry;
                break;
            }
        }

        if (!$rewardEntry) {
            return ['status' => 2, 'result' => 'Invalid exchange requirement.'];
        }

        $currentBadges = $this->badgeCount($char->id);
        if ($currentBadges < $requirement) {
            return ['status' => 2, 'result' => "Not enough Justice Badges (have {$currentBadges}, need {$requirement})."];
        }

        // Deduct badges.
        $badgeItem = CharacterItem::where('character_id', $char->id)
            ->where('item_id', self::MATERIAL)
            ->first();

        if ($badgeItem) {
            $badgeItem->quantity -= $requirement;
            if ($badgeItem->quantity <= 0) {
                $badgeItem->delete();
            } else {
                $badgeItem->save();
            }
        }

        // Grant reward.
        $granter  = new RewardGrantService();
        $levelUp  = $granter->grant($char, (string) $rewardEntry['id']);
        $char->refresh();

        return [
            'status'    => 1,
            'error'     => 0,
            'rewards'   => [$rewardEntry['id']],
            'materials' => $this->badgeCount($char->id),
            'level_up'  => $levelUp,
            'level'     => (int) $char->level,
            'xp'        => (int) $char->xp,
        ];
    }

    // -------------------------------------------------------------------------

    private function badgeCount(int $charId): int
    {
        return (int) CharacterItem::where('character_id', $charId)
            ->where('item_id', self::MATERIAL)
            ->value('quantity');
    }

    private function config(): ?array
    {
        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) return null;
        return $event->data ?? [];
    }
}