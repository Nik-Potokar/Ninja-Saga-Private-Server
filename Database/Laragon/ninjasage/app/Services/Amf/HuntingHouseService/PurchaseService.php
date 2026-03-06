<?php

namespace App\Services\Amf\HuntingHouseService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    use ValidatesSession;

    /**
     * buyMaterial
     */
    public function buyMaterial($charId, $sessionKey, $amount)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF HuntingHouse.buyMaterial: Char $charId Qty $amount");

        $char = Character::find($charId);
        $user = User::find($char->user_id);
        $settings = \App\Models\GameConfig::get('hunting_house_settings', []);
        $price = (int) ($settings['material_price'] ?? 5);
        $totalCost = $price * $amount;

        if ($user->tokens < $totalCost) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        $user->tokens -= $totalCost;
        $user->save();

        $inv = CharacterItem::firstOrCreate(
            ['character_id' => $charId, 'item_id' => 'material_509'],
            ['quantity' => 0, 'category' => 'material']
        );
        $inv->quantity += $amount;
        $inv->save();

        return [
            'status' => 1,
            'material' => (int)$inv->quantity
        ];
    }
}