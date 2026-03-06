<?php

namespace App\Services\Amf\PetService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class ManagementService
{
    use ValidatesSession;

    /**
     * releasePet
     */
    public function releasePet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0];
        $sessionKey = $params[1];
        $petId = $params[2];

        Log::info("AMF PetService.releasePet: Char $charId Pet $petId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        // Find the pet
        $pet = CharacterPet::where('character_id', $charId)->where('id', $petId)->first();

        if (!$pet) {
            return ['status' => 0, 'error' => 'Pet not found'];
        }

        // Check if equipped
        if ($char->equipment_pet == $pet->id) {
            return ['status' => 0, 'error' => 'Cannot release an equipped pet! Unequip it first.'];
        }

        // Delete pet
        $pet->delete();

        return ['status' => 1];
    }

    /**
     * renamePet
     */
    public function renamePet($charIdOrParams, $sessionKey = null, $petId = null, $newName = null)
    {
        if (is_array($charIdOrParams)) {
            $charId = $charIdOrParams[0];
            $sessionKey = $charIdOrParams[1] ?? null;
            $petId = $charIdOrParams[2] ?? null;
            $newName = $charIdOrParams[3] ?? null;
        } else {
            $charId = $charIdOrParams;
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PetService.renamePet: Char $charId Pet $petId Name $newName");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found.'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found.'];

        $requiredQty = ($user->account_type >= 1) ? 1 : 3; // Premium/Admin: 1, Free: 3

        $renameItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', 'essential_01')
            ->first();

        if (!$renameItem || $renameItem->quantity < $requiredQty) {
            return ['status' => 2, 'result' => "You need $requiredQty Rename Badge(s) to rename your pet."];
        }

        $pet = CharacterPet::where('character_id', $charId)->where('id', $petId)->first();

        if (!$pet) {
            return ['status' => 0, 'error' => 'Pet not found.'];
        }

        $pet->update(['name' => $newName]);

        // Deduct item
        $renameItem->quantity -= $requiredQty;
        if ($renameItem->quantity <= 0) {
            $renameItem->delete();
        } else {
            $renameItem->save();
        }

        return ['status' => 1];
    }
}
