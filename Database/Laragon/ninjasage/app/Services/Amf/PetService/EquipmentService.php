<?php

namespace App\Services\Amf\PetService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class EquipmentService
{
    use ValidatesSession;

    /**
     * equipPet
     */
    public function equipPet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0];
        $sessionKey = $params[1];
        $petId = $params[2]; // Instance ID (integer)

        Log::info("AMF PetService.equipPet: Char $charId Pet Instance $petId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        if ($char->rank == 1) {
            return ['status' => 2, 'result' => 'You must be Chunin or higher to use pets!'];
        }

        $pet = CharacterPet::where('character_id', $charId)->where('id', $petId)->first();
        if (!$pet) return ['status' => 0, 'error' => 'Pet not found in your inventory'];

        $char->update(['equipment_pet' => $pet->id]);

        $petConfig = \App\Models\Pet::where('pet_id', $pet->pet_id)->first(); // Use Type ID for config
        $petSwf = $petConfig->swf ?? ('pet_' . $pet->pet_id);

        return [
            'status' => 1,
            'pet_id' => $pet->id, // Instance ID
            'pet_swf' => $petSwf // Type ID
        ];
    }

    /**
     * unequipPet
     */
    public function unequipPet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0];
        Log::info("AMF PetService.unequipPet: Char $charId");
        $char = Character::find($charId);
        if ($char) {
            $char->update(['equipment_pet' => null]);
        }
        return ['status' => 1];
    }
}
