<?php

namespace App\Services\Amf\BlacksmithService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\User;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForgeService
{
    use ValidatesSession;

    private $recipes = [];

    public function __construct()
    {
        $this->recipes = require __DIR__ . '/../BlacksmithRecipes.php';
    }

    /**
     * forgeItem
     * Params: [charId, sessionKey, weaponId, currency]
     */
    public function forgeItem($charId, $sessionKey, $weaponId, $currency)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Blacksmith.forgeItem: Char $charId Wpn $weaponId Currency $currency");

        try {
            return DB::transaction(function () use ($charId, $weaponId, $currency) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $user = User::lockForUpdate()->find($char->user_id);
                if (!$user) {
                    return ['status' => 0, 'error' => 'User not found'];
                }

                $recipe = $this->getRecipe($weaponId);
                if (!$recipe) {
                    return ['status' => 0, 'error' => 'Recipe not found'];
                }

                // Check Currency
                if ($currency !== 'gold' && $currency !== 'tokens') {
                    return ['status' => 0, 'error' => 'Invalid currency'];
                }

                $cost = ($currency == 'gold') ? $recipe['gold'] : $recipe['tokens'];
                if ($currency == 'gold') {
                    if ($char->gold < $cost) return ['status' => 2, 'error' => 0, 'result' => 'Not enough gold!'];
                } else {
                    if ($user->tokens < $cost) return ['status' => 2, 'error' => 0, 'result' => 'Not enough tokens!'];
                }

                $reqWeapon = $recipe['req_weapon'];

                if (!CharacterItem::where('character_id', $charId)->where('item_id', $reqWeapon)->exists()) {
                    $hasWeapon = CharacterItem::where('character_id', $charId)->where('item_id', $reqWeapon)->exists();
                    if (!$hasWeapon && $char->equipment_weapon == $reqWeapon) {
                        $hasWeapon = true;
                    }
                    if (!$hasWeapon) return ['status' => 2, 'error' => 0, 'result' => 'You do not have the required weapon'];
                }

                if ($currency == 'gold') {
                    foreach ($recipe['mats'] as $matId => $qty) {
                        $hasMat = CharacterItem::where('character_id', $charId)->where('item_id', $matId)->value('quantity') ?? 0;
                        if ($hasMat < $qty) {
                            return ['status' => 2, 'error' => 0, 'result' => "Not enough $matId!"];
                        }
                    }
                }

                // Deduct Currency
                if ($currency == 'gold') {
                    $char->gold -= $cost;
                    $char->save();
                } else {
                    $user->tokens -= $cost;
                    $user->save();
                }

                if ($currency == 'gold') {
                    foreach ($recipe['mats'] as $matId => $qty) {
                        $item = CharacterItem::where('character_id', $charId)->where('item_id', $matId)->first();
                        if ($item) {
                            $item->quantity -= $qty;
                            if ($item->quantity <= 0) $item->delete();
                            else $item->save();
                        }
                    }
                }

                if ($char->equipment_weapon == $reqWeapon) {
                    $char->equipment_weapon = 'wpn_01';
                    $char->save();
                } else {
                    $wItem = CharacterItem::where('character_id', $charId)->where('item_id', $reqWeapon)->first();
                    if ($wItem) {
                        $wItem->quantity -= 1;
                        if ($wItem->quantity <= 0) $wItem->delete();
                        else $wItem->save();
                    }
                }

                // Add New Weapon
                $newItem = CharacterItem::where('character_id', $charId)->where('item_id', $weaponId)->first();
                if ($newItem) {
                    $newItem->quantity += 1;
                    $newItem->save();
                } else {
                    CharacterItem::create([
                        'character_id' => $charId,
                        'item_id' => $weaponId,
                        'quantity' => 1,
                        'category' => 'weapon'
                    ]);
                }

                $reqArray = [[], []];
                if ($currency == 'gold') {
                    foreach ($recipe['mats'] as $matId => $qty) {
                        $reqArray[0][] = $matId;
                        $reqArray[1][] = $qty;
                    }
                }
                $reqArray[0][] = $reqWeapon;
                $reqArray[1][] = 1;

                return [
                    'status' => 1,
                    'error' => 0,
                    'result' => 'Weapon Forged!',
                    'item' => $weaponId,
                    'requirements' => $reqArray
                ];
            });
        } catch (\Exception $e) {
            Log::error("Blacksmith Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    private function getRecipe($weaponId)
    {
        // I need to port the switch case from BlacksmithData.as
        // Since I can't run AS3, I'll regex parse it from the file content I read earlier.
        // Or I can just put the logic here.

        // I'll implement a basic parser logic or hardcode common ones.
        // For now, I'll return a mock recipe if not found, to prevent crashes, but warn.

        if (isset($this->recipes[$weaponId])) {
            return $this->recipes[$weaponId];
        }

        // Fallback: Try to parse on the fly? No, slow.
        // I will rely on the property I populate.

        return null;
    }
}
