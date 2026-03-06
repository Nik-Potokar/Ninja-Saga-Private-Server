<?php

namespace App\Services\Amf\PackageEventService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\GameConfig;
use App\Models\Item;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    use ValidatesSession;

    /**
     * buyChuninPackage
     */
    public function buyChuninPackage($charIdOrParams, $sessionKey = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PackageEvent.buyChuninPackage: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $package = GameConfig::get('chunin_package');
        if (!$package) return ['status' => 0, 'error' => 'Package configuration not found'];

        $cost = $package['cost'] ?? 0;

        if ($user->tokens < $cost) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        // Check if already purchased (owns any of the rewards)
        $rewards = $package['rewards'] ?? [];
        foreach ($rewards as $reward) {
            if ($reward['type'] === 'skill') {
                if (CharacterSkill::where('character_id', $char->id)->where('skill_id', $reward['id'])->exists()) {
                    return ['status' => 0, 'error' => 'You already purchased this package!'];
                }
            } elseif ($reward['type'] === 'item') {
                // Check if item exists in inventory
                if (CharacterItem::where('character_id', $char->id)->where('item_id', $reward['id'])->exists()) {
                    return ['status' => 0, 'error' => 'You already purchased this package!'];
                }
            }
        }

        try {
            return DB::transaction(function () use ($user, $char, $cost, $package, $rewards) {
                // Deduct Tokens
                $user->tokens -= $cost;
                $user->save();

                $rewards = $package['rewards'] ?? [];
                foreach ($rewards as $reward) {
                    if ($reward['type'] === 'skill') {
                        $exists = CharacterSkill::where('character_id', $char->id)
                            ->where('skill_id', $reward['id'])->exists();
                        if (!$exists) {
                            CharacterSkill::create([
                                'character_id' => $char->id,
                                'skill_id' => $reward['id']
                            ]);
                        }
                    } elseif ($reward['type'] === 'item') {
                        $invItem = CharacterItem::where('character_id', $char->id)
                            ->where('item_id', $reward['id'])->first();

                        if ($invItem) {
                            $invItem->quantity += 1;
                            $invItem->save();
                        } else {
                            $itemDef = Item::where('item_id', $reward['id'])->first();
                            $cat = $itemDef ? $itemDef->category : 'item';

                            CharacterItem::create([
                                'character_id' => $char->id,
                                'item_id' => $reward['id'],
                                'quantity' => 1,
                                'category' => $cat
                            ]);
                        }
                    }
                }

                return ['status' => 1, 'result' => 'Package purchased successfully!', 'user_tokens' => $user->tokens];
            });
        } catch (\Exception $e) {
            Log::error("Buy Chunin Package Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Transaction failed'];
        }
    }

    /**
     * buyDesignContest
     * Params: [charId, sessionKey, itemId]
     */
    public function buyDesignContest($charId, $sessionKey, $itemId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PackageEvent.buyDesignContest: Char $charId Item $itemId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $item = Item::where('item_id', $itemId)->first();
        if (!$item) return ['status' => 0, 'error' => 'Item not found'];

        // Determine price type and amount
        $priceGold = $item->price_gold;
        $priceTokens = $item->price_tokens;

        if ($priceGold > 0) {
            if ($char->gold < $priceGold) {
                return ['status' => 2, 'result' => 'Not enough gold!'];
            }
            $char->gold -= $priceGold;
            $char->save();
        } elseif ($priceTokens > 0) {
            if ($user->tokens < $priceTokens) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }
            $user->tokens -= $priceTokens;
            $user->save();
        } else {
            // Free? Or error?
            // Assuming free is allowed if price is 0
        }

        // Add to inventory
        $invItem = CharacterItem::where('character_id', $charId)->where('item_id', $itemId)->first();
        if ($invItem) {
            $invItem->quantity += 1;
            $invItem->save();
        } else {
            CharacterItem::create([
                'character_id' => $charId,
                'item_id' => $itemId,
                'quantity' => 1,
                'category' => $item->category
            ]);
        }

        return [
            'status' => 1,
            'result' => 'Item bought successfully!',
            // Client handles the rest visually
        ];
    }
}
