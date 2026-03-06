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

/**
 * ExoticPackageService AMF Service — backs all *Set panels.
 *
 * Admin configures packages via Settings → Exotic Packages.
 * Data is stored in game_configs under key "exotic_packages" as an array:
 * [
 *   {"key": "spiritoforient", "name": "Spirit of Orient Set", "price": 200, "items": ["wpn_1", ...]},
 *   {"key": "necromancer",    "name": "Necromancer Set",       "price": 200, "items": [...]},
 *   ...
 * ]
 *
 * Client calls:
 *   ExoticPackage.get(charId, sessionKey)
 *   ExoticPackage.buy(charId, sessionKey, packageKey)
 */
class ExoticPackageService
{
    use ValidatesSession;

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey
     * Returns: { status: 1, packages: { spiritoforient: {name, price, items}, ... } }
     *
     * The client accesses param1.packages.<key> for the specific set being opened.
     */
    public function get($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ExoticPackage.get: Char $charId");

        $rows     = GameConfig::get('exotic_packages', []);
        $packages = [];

        foreach ($rows as $row) {
            $key = $row['key'] ?? null;
            if (!$key) continue;

            $packages[$key] = [
                'name'  => $row['name']  ?? '',
                'price' => (int) ($row['price'] ?? 0),
                'items' => $row['items'] ?? [],
            ];
        }

        return [
            'status'   => 1,
            'error'    => 0,
            'packages' => $packages,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Params: charId, sessionKey, packageKey  (e.g. "spiritoforient")
     * Returns: { status: 1, rewards: ["item_id", ...] }
     *
     * On success the client calls:
     *   Character.addRewards(param1.rewards)
     *   Character.account_tokens -= package.price   (done client-side)
     */
    public function buy($charId, $sessionKey, $packageKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF ExoticPackage.buy: Char $charId Package $packageKey");

        $rows    = GameConfig::get('exotic_packages', []);
        $package = null;

        foreach ($rows as $row) {
            if (($row['key'] ?? '') === (string) $packageKey) {
                $package = $row;
                break;
            }
        }

        if (!$package) {
            return ['status' => 2, 'result' => 'Package not found.'];
        }

        $price   = (int) ($package['price'] ?? 0);
        $rewards = $package['items'] ?? [];

        return DB::transaction(function () use ($charId, $price, $rewards, $packageKey) {
            $char = Character::lockForUpdate()->find((int) $charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found.'];

            $user = User::lockForUpdate()->find($char->user_id);
            if (!$user) return ['status' => 0, 'error' => 'User not found.'];

            if ($user->tokens < $price) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }

            $user->tokens -= $price;
            $user->save();

            foreach ($rewards as $rewardEntry) {
                $this->grantReward((int) $charId, (string) $rewardEntry);
            }

            Log::info("ExoticPackage purchase complete: Char $charId Package $packageKey Price $price");

            return [
                'status'  => 1,
                'error'   => 0,
                'rewards' => $rewards,
            ];
        });
    }

    // -------------------------------------------------------------------------

    /**
     * Grant a single reward item to a character.
     * Accepts "item_id" or "item_id:qty" format.
     */
    private function grantReward(int $charId, string $entry): void
    {
        $parts    = explode(':', $entry);
        $itemId   = $parts[0];
        $qty      = isset($parts[1]) ? max(1, (int) $parts[1]) : 1;
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