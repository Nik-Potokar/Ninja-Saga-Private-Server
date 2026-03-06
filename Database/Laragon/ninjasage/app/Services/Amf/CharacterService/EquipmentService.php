<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class EquipmentService
{
    use ValidatesSession;

    /**
     * equipSet
     */
    public function equipSet($charId, $sessionKey, $weapon, $backItem, $clothing, $accessory, $hair, $hairColor, $skinColor)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Equip Set: Char $charId");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $char->update([
                'equipment_weapon' => $weapon,
                'equipment_back' => $backItem,
                'equipment_clothing' => $clothing,
                'equipment_accessory' => $accessory,
                'hair_style' => $hair,
                'hair_color' => $hairColor,
                'skin_color' => $skinColor
            ]);

            return ['status' => 1];

        } catch (\Exception $e) {
            Log::error("Equip Set Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * setPoints
     */
    public function setPoints($charId, $sessionKey, $wind, $fire, $lightning, $water, $earth, $free)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Set Points: Char $charId");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $char->update([
                'point_wind' => $wind,
                'point_fire' => $fire,
                'point_lightning' => $lightning,
                'point_water' => $water,
                'point_earth' => $earth,
                'point_free' => $free,
            ]);

            return ['status' => 1, 'error' => 0];

        } catch (\Exception $e) {
            Log::error("Set Points Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * equipSkillSet
     */
    public function equipSkillSet($charId, $sessionKey, $skillString)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Equip Skill Set: Char $charId Skills $skillString");

        try {
            $char = Character::find($charId);
            if (!$char) return ['status' => 0, 'error' => 'Character not found'];

            $char->update([
                'equipment_skills' => $skillString
            ]);

            return ['status' => 1, 'error' => 0];

        } catch (\Exception $e) {
            Log::error("Equip Skill Set Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }
}
