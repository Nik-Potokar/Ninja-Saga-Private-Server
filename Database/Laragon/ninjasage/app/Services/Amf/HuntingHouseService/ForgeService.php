<?php

namespace App\Services\Amf\HuntingHouseService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class ForgeService
{
    use ValidatesSession;

    // -------------------------------------------------------------------------

    /**
     * Load forge recipes: GameConfig first, PHP file as fallback.
     */
    private function loadRecipes(): array
    {
        $config = \App\Models\GameConfig::get('hunting_house_forge_recipes', null);
        if (is_array($config) && !empty($config)) {
            return $config;
        }
        return require __DIR__ . '/HuntingForgeRecipes.php';
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the list of forgeable items and their material requirements.
     * Called by HuntingMarket.as → ForgeDataHunting.constructData(param1.items).
     *
     * Params: charId, sessionKey
     */
    public function getItems($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF HuntingHouse.getItems: Char $charId");

        $rawRecipes = $this->loadRecipes();

        $items = [];
        foreach ($rawRecipes as $outputItem => $recipe) {
            $items[] = [
                'item'         => $outputItem,
                'requirements' => [
                    'materials' => $recipe['materials'],
                    'qty'       => $recipe['qty'],
                ],
            ];
        }

        return [
            'status' => 1,
            'items'  => $items,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Forge an item by consuming the required materials.
     * Called by HuntingMarket.as → onForgeItemRequest.
     *
     * Params: charId, sessionKey, itemId
     *
     * Response the client expects:
     *   status       : 1
     *   item         : "wpn_xxx"          – forged item ID
     *   requirements : [[mat1, mat2], [qty1, qty2]]  – what was consumed
     */
    public function forgeItem($charId, $sessionKey, $itemId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF HuntingHouse.forgeItem: Char $charId Item $itemId");

        $rawRecipes = $this->loadRecipes();

        if (!isset($rawRecipes[$itemId])) {
            return ['status' => 2, 'result' => 'Invalid item or recipe not found.'];
        }

        $recipe    = $rawRecipes[$itemId];
        $materials = $recipe['materials'];
        $qtys      = $recipe['qty'];

        $charId = (int) $charId;

        // Check and deduct materials.
        foreach ($materials as $idx => $matId) {
            $required = (int) ($qtys[$idx] ?? 1);
            $held     = (int) CharacterItem::where('character_id', $charId)
                ->where('item_id', $matId)
                ->value('quantity');

            if ($held < $required) {
                $name = $matId;
                return ['status' => 2, 'result' => "Not enough {$name} (have {$held}, need {$required})."];
            }
        }

        foreach ($materials as $idx => $matId) {
            $required = (int) ($qtys[$idx] ?? 1);
            $matItem  = CharacterItem::where('character_id', $charId)
                ->where('item_id', $matId)
                ->first();

            if ($matItem) {
                $matItem->quantity -= $required;
                if ($matItem->quantity <= 0) {
                    $matItem->delete();
                } else {
                    $matItem->save();
                }
            }
        }

        // Grant the forged item.
        $prefix   = explode('_', $itemId)[0];
        $category = match ($prefix) {
            'wpn'       => 'weapon',
            'back'      => 'back',
            'set'       => 'set',
            'accessory' => 'accessory',
            'hair'      => 'hair',
            'skill'     => 'skill',
            'pet'       => 'pet',
            default     => Item::where('item_id', $itemId)->value('category') ?? 'item',
        };

        $grantedItem = CharacterItem::firstOrCreate(
            ['character_id' => $charId, 'item_id' => $itemId],
            ['quantity' => 0, 'category' => $category]
        );
        $grantedItem->increment('quantity');

        return [
            'status'       => 1,
            'item'         => $itemId,
            'requirements' => [$materials, $qtys],
        ];
    }
}