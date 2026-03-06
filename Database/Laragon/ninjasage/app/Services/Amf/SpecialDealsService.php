<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\GameConfig;
use App\Models\Item;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpecialDealsService
{
    use ValidatesSession;

    // -------------------------------------------------------------------------

    /**
     * Returns the list of active special deal packages.
     * Called by SpecialDeals.as → getData().
     *
     * Deals are stored in game_configs under key "special_deals" as a JSON array:
     * [
     *   {
     *     "id":    1,
     *     "name":  "Starter Pack",
     *     "end":   "Limited Time",
     *     "price": 50,
     *     "items": ["wpn_81", "material_509:5"]
     *   },
     *   ...
     * ]
     *
     * Params: charId, sessionKey
     */
    public function getDeals($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF SpecialDeals.getDeals: Char $charId");

        $deals = GameConfig::get('special_deals', []);

        if (empty($deals)) {
            // Return an empty deals list rather than an error so the panel opens
            // and simply shows nothing (same UX as "no deals available").
            return ['status' => 1, 'deals' => []];
        }

        return ['status' => 1, 'deals' => array_values($deals)];
    }

    // -------------------------------------------------------------------------

    /**
     * Purchase a special deal package.
     * Called by SpecialDeals.as → buyPackage().
     *
     * On success the client calls Character.addRewards(param1.rewards) which
     * accepts an array of item ID strings, optionally with ":qty" suffix:
     *   ["wpn_81", "material_509:5", "essential_01:2"]
     *
     * Params: charId, sessionKey, dealId
     */
    public function buy($charId, $sessionKey, $dealId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF SpecialDeals.buy: Char $charId Deal $dealId");

        $deals = GameConfig::get('special_deals', []);

        $deal = null;
        foreach ($deals as $d) {
            if ((int) ($d['id'] ?? -1) === (int) $dealId) {
                $deal = $d;
                break;
            }
        }

        if (!$deal) {
            return ['status' => 2, 'result' => 'Deal not found.'];
        }

        $price   = (int) ($deal['price'] ?? 0);
        $rewards = $deal['items'] ?? [];

        return DB::transaction(function () use ($charId, $price, $rewards, $deal) {
            $char = Character::lockForUpdate()->find((int) $charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found.'];

            $user = User::lockForUpdate()->find($char->user_id);
            if (!$user) return ['status' => 0, 'error' => 'User not found.'];

            if ($user->tokens < $price) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }

            $user->tokens -= $price;
            $user->save();

            // Grant every reward item.
            foreach ($rewards as $rewardEntry) {
                $this->grantReward((int) $charId, (string) $rewardEntry);
            }

            Log::info("SpecialDeals purchase complete: Char $charId Deal {$deal['id']} Price $price");

            return [
                'status'  => 1,
                'rewards' => $rewards,
            ];
        });
    }

    // -------------------------------------------------------------------------

    /**
     * Grant a single reward item to a character.
     *
     * Accepts "item_id" or "item_id:qty" format.
     * Category is auto-detected from the item ID prefix; falls back to the
     * items table, then defaults to "item".
     */
    private function grantReward(int $charId, string $entry): void
    {
        $parts  = explode(':', $entry);
        $itemId = $parts[0];
        $qty    = isset($parts[1]) ? max(1, (int) $parts[1]) : 1;

        $prefix   = explode('_', $itemId)[0];
        $category = match ($prefix) {
            'wpn'       => 'weapon',
            'back'      => 'back',
            'set'       => 'set',
            'accessory' => 'accessory',
            'hair'      => 'hair',
            'skill'     => 'skill',
            'pet'       => 'pet',
            'material'  => 'material',
            'essential' => 'essential',
            'item'      => 'item',
            default     => Item::where('item_id', $itemId)->value('category') ?? 'item',
        };

        $inv = CharacterItem::firstOrCreate(
            ['character_id' => $charId, 'item_id' => $itemId],
            ['quantity' => 0, 'category' => $category]
        );
        $inv->increment('quantity', $qty);
    }
}
