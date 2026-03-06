<?php

namespace App\Services\Amf;

use App\Models\CharacterItem;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class MaterialMarketService
{
    use ValidatesSession;

    // -------------------------------------------------------------------------

    /**
     * Returns the list of forgeable items and their material requirements.
     * Called by MaterialMarket.as → ForgeData.constructData(param1.items).
     *
     * The Flash client's ForgeData.constructData() stores each entry as:
     *   data[item] = {
     *     "item_materials": requirements.materials,
     *     "item_mat_price":  requirements.qty,
     *     "item_mat_end":    end          ← shown in endTxt; null → "Unavailable"
     *   }
     *
     * Params: charId, sessionKey
     */
    public function getItems($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MaterialMarket.getItems: Char $charId");

        $rawRecipes = require __DIR__ . '/MaterialMarketRecipes.php';

        $items = [];
        foreach ($rawRecipes as $outputItem => $recipe) {
            $items[] = [
                'item'         => $outputItem,
                'requirements' => [
                    'materials' => $recipe['materials'],
                    'qty'       => $recipe['qty'],
                ],
                'end'          => $recipe['end'] ?? null,
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
     * Called by MaterialMarket.as → onForgeItemRequest.
     *
     * Response the client expects (onForgeItemResponse):
     *   status 1  → success
     *     item         : "wpn_xxx"
     *     requirements : [[mat1, mat2, ...], [qty1, qty2, ...]]
     *   status 2  → soft error (shows notice)
     *   status 0  → hard error (shows getError)
     *
     * Params: charId, sessionKey, itemId
     */
    public function forgeItem($charId, $sessionKey, $itemId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MaterialMarket.forgeItem: Char $charId Item $itemId");

        $rawRecipes = require __DIR__ . '/MaterialMarketRecipes.php';

        if (!isset($rawRecipes[$itemId])) {
            return ['status' => 2, 'result' => 'Invalid item or recipe not found.'];
        }

        $recipe    = $rawRecipes[$itemId];
        $materials = $recipe['materials'];
        $qtys      = $recipe['qty'];
        $charId    = (int) $charId;

        // Check all materials before deducting anything.
        foreach ($materials as $idx => $matId) {
            $required = (int) ($qtys[$idx] ?? 1);
            $held     = (int) CharacterItem::where('character_id', $charId)
                ->where('item_id', $matId)
                ->value('quantity');

            if ($held < $required) {
                return ['status' => 2, 'result' => "Not enough {$matId} (have {$held}, need {$required})."];
            }
        }

        // Deduct materials.
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

        // Determine category from item ID prefix.
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

        // Grant the forged item.
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
