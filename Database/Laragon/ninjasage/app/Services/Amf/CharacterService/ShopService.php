<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\Item;
use App\Models\Skill;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopService
{
    use ValidatesSession;

    private static ?array $libraryIndex = null;
    private static ?array $animationIndex = null;

    /**
     * buySkill
     */
    public function buySkill($sessionKey, $charId, $skillId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Buy Skill: Char $charId Skill $skillId");

        try {
            return DB::transaction(function () use ($charId, $skillId) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $user = User::lockForUpdate()->find($char->user_id);
                if (!$user) return ['status' => 0, 'error' => 'User not found'];

                if (CharacterSkill::where('character_id', $charId)->where('skill_id', $skillId)->exists()) {
                    return ['status' => 2];
                }

                $skill = Skill::where('skill_id', $skillId)->first();
                if (!$skill) return ['status' => 0, 'error' => 'Skill data not found!'];

                // Premium Check
                if ($skill->premium && $user->account_type == 0) {
                    return ['status' => 6]; // Triggers EmblemUpgrade popup
                }

                if ($char->level < $skill->level) return ['status' => 5];

                if ($skill->element >= 1 && $skill->element <= 5) {
                    $myElements = array_filter([$char->element_1, $char->element_2, $char->element_3]);
                    if (!in_array($skill->element, $myElements)) {
                        $maxElements = ($user->account_type >= 1) ? 3 : 2;
                        if (count($myElements) < $maxElements) {
                            if (!$char->element_1) {
                                $char->element_1 = $skill->element;
                            } elseif (!$char->element_2) {
                                $char->element_2 = $skill->element;
                            } elseif (!$char->element_3) {
                                $char->element_3 = $skill->element;
                            }
                            $char->save();
                        } else {
                            return ['status' => 4];
                        }
                    }
                }

                if ($char->gold < $skill->price_gold || $user->tokens < $skill->price_tokens) return ['status' => 3];

                $char->gold -= $skill->price_gold;
                $char->save();

                if ($skill->price_tokens > 0) {
                    $user->tokens -= $skill->price_tokens;
                    $user->save();
                }

                CharacterSkill::create(['character_id' => $charId, 'skill_id' => $skillId]);

                return [
                    'status' => 1,
                    'data' => [
                        'character_gold' => $char->gold,
                        'account_tokens' => $user->tokens,
                        'character_element_1' => $char->element_1,
                        'character_element_2' => $char->element_2,
                        'character_element_3' => $char->element_3
                    ]
                ];
            });

        } catch (\Exception $e) {
            Log::error("Buy Skill Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * buyItem
     */
    public function buyItem($charId, $sessionKey, $itemId, $quantity)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Buy Item: Char $charId Item $itemId Qty $quantity");

        if ($quantity <= 0) {
            return ['status' => 0, 'error' => 'Invalid quantity'];
        }

        if (str_starts_with($itemId, 'skill_')) {
            return $this->buySkill($sessionKey, $charId, $itemId);
        }

        try {
            return DB::transaction(function () use ($charId, $itemId, $quantity) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $item = Item::where('item_id', $itemId)->first();
                if (!$item) return ['status' => 0, 'error' => 'Item data not found!'];

                $user = User::lockForUpdate()->find($char->user_id);
                if (!$user) return ['status' => 0, 'error' => 'User not found'];

                // Premium Check
                if ($item->premium && $user->account_type == 0) {
                    return ['status' => 6, 'result' => 'Upgrade premium to buy!']; // Triggers EmblemUpgrade popup
                }

                if ($char->level < $item->level) return ['status' => 5, 'result' => 'Level too low!'];

                $totalGold   = $item->price_gold   * $quantity;
                $totalTokens = $item->price_tokens * $quantity;

                // PvP points price — read from library.json since items table has no price_pvp column
                $libraryEntry = $this->getLibraryItem($itemId);
                $totalPvp = isset($libraryEntry['price_pvp']) ? (int)$libraryEntry['price_pvp'] * $quantity : 0;

                if ($char->gold < $totalGold || $user->tokens < $totalTokens) {
                    return ['status' => 3, 'result' => 'Not enough resources!'];
                }

                if ($totalPvp > 0 && $char->pvp_points < $totalPvp) {
                    return ['status' => 3, 'result' => 'Not enough PvP points!'];
                }

                $char->gold -= $totalGold;

                if ($totalPvp > 0) {
                    $char->pvp_points -= $totalPvp;
                }

                $char->save();

                if ($totalTokens > 0) {
                    $user->tokens -= $totalTokens;
                    $user->save();
                }

                $invItem = CharacterItem::where('character_id', $charId)->where('item_id', $itemId)->first();
                if ($invItem) {
                    $invItem->quantity += $quantity;
                    $invItem->save();
                } else {
                    CharacterItem::create([
                        'character_id' => $charId, 'item_id' => $itemId, 'quantity' => $quantity, 'category' => $item->category
                    ]);
                }

                return [
                    'status' => 1,
                    'error' => 0,
                    'data' => [
                        'character_gold'     => $char->gold,
                        'character_prestige' => $char->prestige,
                        'account_tokens'     => $user->tokens,
                        'pvp_points'         => $char->pvp_points,
                    ]
                ];
            });

        } catch (\Exception $e) {
            Log::error("Buy Item Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * sellItem
     */
    public function sellItem($charId, $sessionKey, $itemId, $quantity)
    {
        if (is_array($charId)) {
            $sessionKey = $charId[1] ?? null;
            $itemId = $charId[2] ?? null;
            $quantity = $charId[3] ?? null;
            $charId = $charId[0] ?? null;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Sell Item: Char $charId Item $itemId Qty $quantity");

        try {
            return DB::transaction(function () use ($charId, $itemId, $quantity) {
                $qty = (int) $quantity;
                if ($qty <= 0) {
                    return ['status' => 0, 'error' => 'Invalid quantity'];
                }

                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $invItem = CharacterItem::lockForUpdate()
                    ->where('character_id', $charId)
                    ->where('item_id', $itemId)
                    ->first();
                if (!$invItem || $invItem->quantity < $qty) {
                    return ['status' => 0, 'error' => 'Not enough items to sell!'];
                }

                $itemConfig = Item::where('item_id', $itemId)->first();
                if (!$itemConfig) {
                    return ['status' => 0, 'error' => 'Item data not found!'];
                }

                // Calculate Sell Price (DB first; fallback to library sell_price only when needed)
                $basePrice = (int) ($itemConfig->price_gold ?? 0);
                $libraryItem = null;
                if ($basePrice > 0) {
                    $sellPriceOne = (int) floor($basePrice / 2);
                } else {
                    $libraryItem = $this->getLibraryItem($itemId);
                    $sellPriceOne = (int) ($libraryItem['sell_price'] ?? 0);
                }

                if ($sellPriceOne <= 0) {
                    return ['status' => 0, 'error' => 'Item cannot be sold!'];
                }

                if ($libraryItem === null && $basePrice <= 0) {
                    $libraryItem = $this->getLibraryItem($itemId);
                }
                if ($libraryItem && array_key_exists('sellable', $libraryItem) && !$libraryItem['sellable']) {
                    return ['status' => 0, 'error' => 'Item cannot be sold!'];
                }
                $totalSellPrice = $sellPriceOne * $qty;

                $char->gold += $totalSellPrice;
                $char->save();

                if ($invItem->quantity == $qty) {
                    $invItem->delete();
                } else {
                    $invItem->quantity -= $qty;
                    $invItem->save();
                }

                return [
                    'status' => 1,
                    'error' => 0,
                    'data' => [
                        'character_gold' => $char->gold
                    ]
                ];
            });

        } catch (\Exception $e) {
            Log::error("Sell Item Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * buyGanMaterial
     * Params: [sessionKey, charId, quantity]
     */
    public function buyGanMaterial($sessionKey, $charId, $quantity)
    {
        if (is_array($sessionKey)) {
            $charId = $sessionKey[1];
            $quantity = $sessionKey[2];
            $sessionKey = $sessionKey[0];
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF buyGanMaterial: Char $charId Qty $quantity");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $user = User::find($char->user_id);
            if (!$user) return ['status' => 0, 'error' => 'User not found'];

            // Ninja Seal Gan ID
            $itemId = 'material_1001';
            $item = Item::where('item_id', $itemId)->first();

            if (!$item) {
                return ['status' => 0, 'error' => 'Item not found'];
            }

            $totalCost = $item->price_tokens * $quantity;

            if ($user->tokens < $totalCost) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }

            // Deduct Tokens
            $user->tokens -= $totalCost;
            $user->save();

            // Add Item
            $charItem = CharacterItem::where('character_id', $charId)
                ->where('item_id', $itemId)
                ->first();

            if ($charItem) {
                $charItem->quantity += $quantity;
                $charItem->save();
            } else {
                $charItem = CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'category' => $item->category ?? 'item'
                ]);
            }

            return [
                'status' => 1,
                'gan' => $charItem->quantity
            ];

        } catch (\Exception $e) {
            Log::error("Buy Gan Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * buyTalentEssential
     * Params: [sessionKey, charId, quantity]
     */
    public function buyTalentEssential($sessionKey, $charId, $quantity)
    {
        if (is_array($sessionKey)) {
            $charId = $sessionKey[1];
            $quantity = $sessionKey[2];
            $sessionKey = $sessionKey[0];
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF buyTalentEssential: Char $charId Qty $quantity");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $user = User::find($char->user_id);
            if (!$user) return ['status' => 0, 'error' => 'User not found'];

            $itemId = 'essential_02';
            $item = Item::where('item_id', $itemId)->first();

            if (!$item) return ['status' => 0, 'error' => 'Item not found'];

            $totalCost = $item->price_tokens * $quantity;

            if ($user->tokens < $totalCost) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }

            $user->tokens -= $totalCost;
            $user->save();

            $charItem = CharacterItem::where('character_id', $charId)
                ->where('item_id', $itemId)
                ->first();

            if ($charItem) {
                $charItem->quantity += $quantity;
                $charItem->save();
            } else {
                $charItem = CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'category' => $item->category ?? 'essential'
                ]);
            }

            return [
                'status' => 1,
                'essential' => $charItem->quantity
            ];

        } catch (\Exception $e) {
            Log::error("Buy Essential Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * buyRenameBadge
     * Params: [sessionKey, charId, quantity]
     */
    public function buyRenameBadge($sessionKey, $charId, $quantity)
    {
        if (is_array($sessionKey)) {
            $charId = $sessionKey[1];
            $quantity = $sessionKey[2];
            $sessionKey = $sessionKey[0];
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF buyRenameBadge: Char $charId Qty $quantity");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $user = User::find($char->user_id);
            if (!$user) return ['status' => 0, 'error' => 'User not found'];

            $itemId = 'essential_01';
            $item = Item::where('item_id', $itemId)->first();

            if (!$item) return ['status' => 0, 'error' => 'Item not found'];

            $totalCost = $item->price_tokens * $quantity;

            if ($user->tokens < $totalCost) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }

            $user->tokens -= $totalCost;
            $user->save();

            $charItem = CharacterItem::where('character_id', $charId)
                ->where('item_id', $itemId)
                ->first();

            if ($charItem) {
                $charItem->quantity += $quantity;
                $charItem->save();
            } else {
                $charItem = CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'category' => $item->category ?? 'essential'
                ]);
            }

            return [
                'status' => 1,
                'essential' => $charItem->quantity
            ];

        } catch (\Exception $e) {
            Log::error("Buy Rename Badge Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * useItem
     * Params: [charId, sessionKey, itemId]
     */
    public function useItem($charId, $sessionKey = null, $itemId = null)
    {
        if (is_array($charId)) {
            $sessionKey = $charId[1] ?? null;
            $itemId = $charId[2] ?? null;
            $charId = $charId[0] ?? null;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        if (!$itemId) {
            return ['status' => 0, 'error' => 'Invalid item'];
        }

        Log::info("AMF Character.useItem: Char $charId Item $itemId");

        try {
            return DB::transaction(function () use ($charId, $itemId) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $invItem = CharacterItem::lockForUpdate()
                    ->where('character_id', $charId)
                    ->where('item_id', $itemId)
                    ->first();

                if (!$invItem || $invItem->quantity <= 0) {
                    return ['status' => 2, 'result' => 'Item not found in inventory'];
                }

                $itemConfig = $this->getLibraryItem($itemId);
                if ($itemConfig && empty($itemConfig['usable'])) {
                    return ['status' => 2, 'result' => 'Item cannot be used'];
                }

                if ($itemConfig && isset($itemConfig['level']) && $char->level < (int)$itemConfig['level']) {
                    return ['status' => 2, 'result' => 'Level too low'];
                }

                $rewardList = $this->resolveUseItemRewards($char, $itemId);
                if ($rewardList === null) {
                    return ['status' => 2, 'result' => 'Item cannot be used'];
                }

                $this->applyUseItemRewards($char, $rewardList);

                if ($invItem->quantity == 1) {
                    $invItem->delete();
                } else {
                    $invItem->quantity -= 1;
                    $invItem->save();
                }

                $char->save();

                return [
                    'status' => 1,
                    'result' => 'Item used',
                    'rewards' => $rewardList
                ];
            });
        } catch (\Exception $e) {
            Log::error("Use Item Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * useBattleItem
     * Params: [sessionKey, charId, itemId]
     */
    public function useBattleItem($sessionKey, $charId = null, $itemId = null)
    {
        if (is_array($sessionKey)) {
            $charId = $sessionKey[1] ?? null;
            $itemId = $sessionKey[2] ?? null;
            $sessionKey = $sessionKey[0] ?? null;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        if (!$itemId) {
            return ['status' => 0, 'error' => 'Invalid item'];
        }

        Log::info("AMF Character.useBattleItem: Char $charId Item $itemId");

        try {
            return DB::transaction(function () use ($charId, $itemId) {
                $invItem = CharacterItem::lockForUpdate()
                    ->where('character_id', $charId)
                    ->where('item_id', $itemId)
                    ->first();

                if (!$invItem || $invItem->quantity <= 0) {
                    return ['status' => 2, 'result' => 'Item not found in inventory'];
                }

                $itemConfig = $this->getLibraryItem($itemId);
                if ($itemConfig && ($itemConfig['type'] ?? '') !== 'item') {
                    return ['status' => 2, 'result' => 'Item cannot be used'];
                }

                if ($invItem->quantity == 1) {
                    $invItem->delete();
                } else {
                    $invItem->quantity -= 1;
                    $invItem->save();
                }

                return [
                    'status' => 1,
                    'result' => 'Item used'
                ];
            });
        } catch (\Exception $e) {
            Log::error("Use Battle Item Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * buyAnimation
     */
    public function buyAnimation($charId, $sessionKey, $animationId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        $animation = $this->getAnimation($animationId);
        if (!$animation) {
            return ['status' => 0, 'error' => 'Animation not found'];
        }

        if (empty($animation['buyable'])) {
            return ['status' => 2, 'result' => 'Animation cannot be purchased'];
        }

        return DB::transaction(function () use ($charId, $animation, $animationId) {
            $char = Character::lockForUpdate()->find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $user = User::lockForUpdate()->find($char->user_id);
            if (!$user) return ['status' => 0, 'error' => 'User not found'];

            if (!empty($animation['premium']) && $user->account_type == 0) {
                return ['status' => 6, 'result' => 'Upgrade premium to buy!'];
            }

            $alreadyOwned = CharacterItem::where('character_id', $charId)
                ->where('item_id', $animationId)
                ->where('category', 'animation')
                ->exists();

            if ($alreadyOwned) {
                return ['status' => 2, 'result' => 'Animation already owned'];
            }

            $price = (int) ($animation['price'] ?? 0);
            if ($price <= 0) {
                return ['status' => 2, 'result' => 'Animation cannot be purchased'];
            }

            if ($user->tokens < $price) {
                return ['status' => 2, 'result' => 'Not enough tokens!'];
            }

            $user->tokens -= $price;
            $user->save();

            CharacterItem::create([
                'character_id' => $charId,
                'item_id' => $animationId,
                'quantity' => 1,
                'category' => 'animation'
            ]);

            return [
                'status' => 1,
                'data' => [
                    'account_tokens' => $user->tokens
                ]
            ];
        });
    }

    /**
     * useAnimation
     */
    public function useAnimation($charId, $sessionKey, $animationId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        $animation = $this->getAnimation($animationId);
        if (!$animation) {
            return ['status' => 0, 'error' => 'Animation not found'];
        }

        $owned = CharacterItem::where('character_id', $charId)
            ->where('item_id', $animationId)
            ->where('category', 'animation')
            ->exists();

        if (!$owned && ($animation['price'] ?? 0) > 0) {
            return ['status' => 2, 'result' => 'Animation not owned'];
        }

        return ['status' => 1];
    }

    private function resolveUseItemRewards(Character $char, string $itemId): ?array
    {
        $tpRewards = [
            'essential_03' => 5,
            'essential_04' => 10,
            'essential_05' => 20,
            'essential_06' => 50,
            'essential_07' => 100,
            'essential_08' => 200,
        ];

        if (isset($tpRewards[$itemId])) {
            if ($char->rank < 5) {
                return null;
            }

            return ['tp_' . $tpRewards[$itemId]];
        }

        if ($itemId === 'essential_12') {
            return [
                'set_831_%s',
                'hair_354_%s',
                'wpn_664',
                'back_350'
            ];
        }

        if ($itemId === 'essential_13') {
            return [
                'set_2179_%s',
                'hair_2144_%s',
                'back_2192'
            ];
        }

        if ($itemId === 'essential_14') {
            $rewards = ['item_55', 'item_56', 'item_57'];
            return [$rewards[array_rand($rewards)]];
        }

        return null;
    }

    private function applyUseItemRewards(Character $char, array $rewardList): void
    {
        foreach ($rewardList as $reward) {
            if (!is_string($reward) || $reward === '') {
                continue;
            }

            $rewardParts = explode('_', $reward);
            $rewardType = $rewardParts[0];

            if ($rewardType === 'tp') {
                $amount = (int)($rewardParts[1] ?? 0);
                if ($amount > 0) {
                    $char->tp += $amount;
                }
                continue;
            }

            $itemSpecParts = explode(':', $reward);
            $itemId = $itemSpecParts[0];
            $quantity = isset($itemSpecParts[1]) ? (int)$itemSpecParts[1] : 1;
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $resolvedItemId = $this->resolveGenderItemId($itemId, $char);
            $category = $this->resolveItemCategory($resolvedItemId);

            $existingItem = CharacterItem::lockForUpdate()
                ->where('character_id', $char->id)
                ->where('item_id', $resolvedItemId)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $quantity;
                $existingItem->save();
            } else {
                CharacterItem::create([
                    'character_id' => $char->id,
                    'item_id' => $resolvedItemId,
                    'quantity' => $quantity,
                    'category' => $category
                ]);
            }
        }
    }

    private function resolveGenderItemId(string $itemId, Character $char): string
    {
        if (str_contains($itemId, '%s')) {
            $suffix = $char->gender == 0 ? '0' : '1';
            return str_replace('%s', $suffix, $itemId);
        }

        return $itemId;
    }

    private function resolveItemCategory(string $itemId): string
    {
        $itemConfig = Item::where('item_id', $itemId)->first();
        if ($itemConfig && $itemConfig->category) {
            return $itemConfig->category;
        }

        $prefix = explode('_', $itemId)[0] ?? 'item';
        return match ($prefix) {
            'wpn' => 'weapon',
            'back' => 'back',
            'accessory' => 'accessory',
            'set' => 'set',
            'hair' => 'hair',
            'material' => 'material',
            'essential' => 'essential',
            default => 'item'
        };
    }

    private function getLibraryItem(string $itemId): ?array
    {
        if (self::$libraryIndex === null) {
            $path = base_path('public/game_data/library.json');
            if (!file_exists($path)) {
                self::$libraryIndex = [];
            } else {
                $raw = file_get_contents($path);
                $items = json_decode($raw, true);
                $index = [];
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (isset($item['id'])) {
                            $index[$item['id']] = $item;
                        }
                    }
                }
                self::$libraryIndex = $index;
            }
        }

        return self::$libraryIndex[$itemId] ?? null;
    }

    private function getAnimation(string $animationId): ?array
    {
        if (self::$animationIndex === null) {
            $path = base_path('public/game_data/animation.json');
            if (!file_exists($path)) {
                self::$animationIndex = [];
            } else {
                $raw = file_get_contents($path);
                $animations = json_decode($raw, true);
                $index = [];
                if (is_array($animations)) {
                    foreach ($animations as $animation) {
                        if (isset($animation['id'])) {
                            $index[$animation['id']] = $animation;
                        }
                    }
                }
                self::$animationIndex = $index;
            }
        }

        return self::$animationIndex[$animationId] ?? null;
    }
}