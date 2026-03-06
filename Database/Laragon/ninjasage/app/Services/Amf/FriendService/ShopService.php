<?php

namespace App\Services\Amf\FriendService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\GameConfig;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopService
{
    use ValidatesSession;

    /**
     * getItems
     * Params: [charId, sessionKey]
     */
    public function getItems($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.getItems: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $catalog = $this->getCatalog();

        return [
            'status' => 1,
            'items' => $catalog,
        ];
    }

    /**
     * buyItem
     * Params: [charId, sessionKey, exchangeId]
     */
    public function buyItem($charId, $sessionKey, $exchangeId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF FriendService.buyItem: Char $charId Exchange $exchangeId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'result' => 'Character not found'];

        $catalog = collect($this->getCatalog())->keyBy('id');
        if (!$catalog->has($exchangeId)) {
            return ['status' => 2, 'result' => 'Item not found.'];
        }

        $entry = $catalog->get($exchangeId);
        $itemSpec = $entry['item'];
        $price = (int)$entry['price'];

        $itemParts = explode(':', $itemSpec);
        $itemId = $itemParts[0];
        $quantity = isset($itemParts[1]) ? max(1, (int)$itemParts[1]) : 1;

        return DB::transaction(function () use ($charId, $itemId, $quantity, $price, $itemSpec) {
            $kunai = CharacterItem::lockForUpdate()
                ->where('character_id', $charId)
                ->where('item_id', FriendData::FRIENDSHIP_KUNAI)
                ->first();

            $currentKunai = $kunai ? (int)$kunai->quantity : 0;
            if ($currentKunai < $price) {
                return ['status' => 2, 'result' => 'Not enough Friendship Kunai.'];
            }

            if ($kunai) {
                $kunai->quantity = $currentKunai - $price;
                $kunai->save();
            }

            $item = Item::where('item_id', $itemId)->first();
            $category = $item ? $item->category : null;

            $charItem = CharacterItem::lockForUpdate()
                ->where('character_id', $charId)
                ->where('item_id', $itemId)
                ->first();

            if ($charItem) {
                $charItem->quantity += $quantity;
                $charItem->save();
            } else {
                CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'category' => $category ?? 'item',
                ]);
            }

            return [
                'status' => 1,
                'reward' => $itemSpec,
                'kunai' => $kunai ? (int)$kunai->quantity : 0,
            ];
        });
    }

    private function getCatalog(): array
    {
        $config = GameConfig::get('friendship_shop', []);
        if (is_array($config) && count($config) > 0) {
            return array_values(array_map(function ($entry, $index) {
                return [
                    'id' => $entry['id'] ?? $index,
                    'item' => $entry['item'],
                    'price' => (int)($entry['price'] ?? 1),
                ];
            }, $config, array_keys($config)));
        }

        $fallbackItems = Item::orderBy('item_id')->limit(10)->get();
        $fallback = [];
        $index = 0;
        foreach ($fallbackItems as $item) {
            $price = $item->price_tokens ?: (int)ceil(($item->price_gold ?: 1000) / 1000);
            $fallback[] = [
                'id' => $index++,
                'item' => $item->item_id,
                'price' => max(1, (int)$price),
            ];
        }

        return $fallback;
    }
}
