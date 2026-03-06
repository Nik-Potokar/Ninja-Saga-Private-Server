<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterEventData;
use App\Models\GameEvent;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

/**
 * NewYear2026 AMF Service
 *
 * Handles the one-time New Year gift claim inside the ChristmasMenu panel.
 * The Flash client calls this separately from ChristmasEvent2025.*:
 *   ChristmasMenu.as → claimNewYear() → "NewYear2026.claim"
 *
 * Rewards come from config["new_year"][] on the active ChristmasMenu GameEvent row.
 * Each character can claim once per event season (tracked in CharacterEventData extra).
 *
 * Expected response: { status: 1, rewards: string[] }
 */
class NewYear2026Service
{
    use ValidatesSession;

    private const PANEL     = 'ChristmasMenu';
    private const EVENT_KEY = 'christmas_2025';

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey
     * Returns: { status: 1, rewards: string[] }
     */
    public function claim($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF NewYear2026.claim: Char $charId");

        $event = GameEvent::where('panel', self::PANEL)->where('active', true)->first();
        if (!$event) {
            return ['status' => 2, 'result' => 'New Year event is currently inactive.'];
        }

        $config    = $event->data ?? [];
        $newYear   = $config['new_year'] ?? [];

        if (empty($newYear)) {
            return ['status' => 2, 'result' => 'No New Year rewards configured.'];
        }

        // Check if already claimed (stored in extra JSON on the battle event data row)
        $eventData = CharacterEventData::forCharacter((int) $charId, self::EVENT_KEY, 10);
        $extra     = $eventData->extra ?? [];

        if (!empty($extra['new_year_claimed'])) {
            return ['status' => 2, 'result' => 'New Year reward already claimed.'];
        }

        $char = Character::with('user')->find((int) $charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $granter = new RewardGrantService();
        $granted = [];
        foreach ($newYear as $rewardStr) {
            $granter->grant($char, (string) $rewardStr);
            // Resolve %s gender placeholder for display
            $displayId = str_replace('%s', (string) ((int) $char->gender === 0 ? 0 : 1), explode(':', $rewardStr)[0]);
            $granted[] = $displayId;
        }

        $extra['new_year_claimed'] = true;
        $eventData->extra          = $extra;
        $eventData->save();

        return [
            'status'  => 1,
            'error'   => 0,
            'rewards' => array_values($granted),
        ];
    }
}
