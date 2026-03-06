<?php

namespace App\Services\Amf\PetService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class QueryService
{
    use ValidatesSession;

    /**
     * getPets
     */
    public function getPets($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0];
        Log::info("AMF PetService.getPets: Char $charId");

        $char = Character::find($charId);
        $pets = CharacterPet::where('character_id', $charId)->get();

        // Auto-equip if only 1 pet exists
        if ($pets->count() === 1 && $char && empty($char->equipment_pet)) {
            $char->update(['equipment_pet' => $pets->first()->id]);
            $char->equipment_pet = $pets->first()->id;
        }

        if ($char && $char->equipment_pet) {
            $pets = $pets
                ->sortByDesc(fn ($pet) => $pet->id === $char->equipment_pet)
                ->values();
        }

        $petList = [];
        foreach ($pets as $p) {
            $config = \App\Models\Pet::where('pet_id', $p->pet_id)->first();
            $petList[] = [
                'pet' => $p->id,
                'char_id' => $charId,
                'pet_id' => $p->id, // Instance ID for client logic
                'pet_name' => $p->name ?? ($config->name ?? 'Unknown Pet'),
                'pet_xp' => $p->xp,
                'pet_level' => $p->level,
                'pet_skills' => $config ? $config->calculateSkillsString($p->level) : "1,1,1,1,1,1",
                'pet_mp' => (int) $p->maturity_points,
                'pet_swf' => $p->pet_id // Type ID for SWF loading
            ];
        }

        return [
            'status' => 1,
            'pets' => $petList
        ];
    }
}
