<?php

namespace App\Services\Amf\ShadowWarService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Models\Pet;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PresetService
{
    use ValidatesSession;

    private const PRESET_COUNT = 4;

    /**
     * getPresets
     * Params: [charId, sessionKey]
     */
    public function getPresets($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        Log::info("AMF ShadowWar.getPresets: Char $charId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $presets = $this->loadPresets($char);

        return [
            'status' => 1,
            'error' => 0,
            'active' => $presets['active'],
            'presets' => $presets['presets'],
        ];
    }

    /**
     * savePreset
     * Params: [charId, sessionKey, presetId, name, weapon, clothing, hair, backItem, accessory, hairColor, skills, petId]
     */
    public function savePreset($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        $presetId = (int)($params[2] ?? 0);
        Log::info("AMF ShadowWar.savePreset: Char $charId Preset $presetId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $presets = $this->loadPresets($char);

        $name = $params[3] ?? ("#Defender Preset $presetId");
        $weapon = $params[4] ?? $char->equipment_weapon;
        $clothing = $params[5] ?? $char->equipment_clothing;
        $hair = $params[6] ?? $char->hair_style;
        $back = $params[7] ?? $char->equipment_back;
        $accessory = $params[8] ?? $char->equipment_accessory;
        $hairColor = $params[9] ?? ($char->hair_color ?: '0|0');
        $skills = $this->normalizeSkills($params[10] ?? null, $char);
        $petId = (int)($params[11] ?? 0);

        $petData = $this->buildPetPreset($char, $petId);

        $presets['presets'][$presetId] = [
            'id' => $presetId,
            'name' => $name,
            'weapon' => $weapon ?: 'wpn_01',
            'clothing' => $clothing ?: 'set_01_0',
            'hair' => $hair ?: 'hair_01_0',
            'back_item' => $back ?: 'back_01',
            'accessory' => $accessory ?: 'accessory_01',
            'hair_color' => $hairColor ?: '0|0',
            'skin_color' => $char->skin_color ?: 'null|null',
            'skills' => $skills,
            'pet' => $petData,
        ];

        Cache::put($this->presetCacheKey($charId), $presets, 86400);

        return [
            'status' => 1,
            'error' => 0,
            'result' => '/Preset has been updated',
        ];
    }

    /**
     * usePreset
     * Params: [charId, sessionKey, presetId]
     */
    public function usePreset($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0] ?? null;
        $presetId = (int)($params[2] ?? 0);
        Log::info("AMF ShadowWar.usePreset: Char $charId Preset $presetId");

        $char = $charId ? Character::find($charId) : null;
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $presets = $this->loadPresets($char);
        $presets['active'] = $presetId;

        Cache::put($this->presetCacheKey($charId), $presets, 86400);

        return [
            'status' => 1,
            'error' => 0,
        ];
    }

    private function loadPresets(Character $char): array
    {
        $cacheKey = $this->presetCacheKey($char->id);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            $normalized = $cached;
            foreach ($normalized['presets'] as $index => $preset) {
                $normalized['presets'][$index]['skills'] = $this->normalizeSkills($preset['skills'] ?? null, $char);
            }
            Cache::put($cacheKey, $normalized, 86400);
            return $normalized;
        }

        $genderSuffix = $char->gender == 0 ? '_0' : '_1';
        $hair = is_numeric($char->hair_style)
            ? 'hair_' . str_pad($char->hair_style, 2, '0', STR_PAD_LEFT) . $genderSuffix
            : ($char->hair_style ?: 'hair_01' . $genderSuffix);

        $petData = $this->buildPetPreset($char, $char->equipment_pet ?: 0);
        $skills = $this->normalizeSkills($char->equipment_skills, $char);

        $presets = [];
        for ($i = 0; $i < self::PRESET_COUNT; $i++) {
            $presets[] = [
                'id' => $i,
                'name' => "#Defender Preset $i",
                'weapon' => $char->equipment_weapon ?: 'wpn_01',
                'clothing' => $char->equipment_clothing ?: 'set_01' . $genderSuffix,
                'hair' => $hair,
                'back_item' => $char->equipment_back ?: 'back_01',
                'accessory' => $char->equipment_accessory ?: 'accessory_01',
                'hair_color' => $char->hair_color ?: '0|0',
                'skin_color' => $char->skin_color ?: 'null|null',
                'skills' => $skills,
                'pet' => $petData,
            ];
        }

        $data = [
            'active' => 0,
            'presets' => $presets,
        ];

        Cache::put($cacheKey, $data, 86400);
        return $data;
    }

    private function normalizeSkills(?string $skills, Character $char): string
    {
        $skills = $skills ? trim($skills) : '';
        if ($skills !== '' && str_starts_with($skills, '!')) {
            $skills = ltrim($skills, '!');
        }

        if ($skills === '') {
            $elementSkillMap = [
                1 => 'skill_13',
                2 => 'skill_10',
                3 => 'skill_01',
                4 => 'skill_12',
                5 => 'skill_09',
            ];
            $skills = $elementSkillMap[$char->element_1] ?? 'skill_01';
        }

        return $skills;
    }

    private function buildPetPreset(Character $char, int $petInstanceId): array
    {
        if (!$petInstanceId) {
            return [
                'pet_swf' => null,
                'pet_id' => null,
            ];
        }

        $petInstance = CharacterPet::where('character_id', $char->id)
            ->where('id', $petInstanceId)
            ->first();

        if (!$petInstance) {
            return [
                'pet_swf' => null,
                'pet_id' => null,
            ];
        }

        $petConfig = Pet::where('pet_id', $petInstance->pet_id)->first();

        return [
            'pet_swf' => $petConfig->swf ?? ('pet_' . $petInstance->pet_id),
            'pet_id' => $petInstance->id,
        ];
    }

    /**
     * Return the currently active preset for a character (no auth check).
     * Used by BattleService to build the enemy's battle loadout.
     */
    public function getActivePreset(int $charId): ?array
    {
        $char = Character::find($charId);
        if (!$char) return null;

        $presets = $this->loadPresets($char);
        $activeIndex = (int)($presets['active'] ?? 0);
        return $presets['presets'][$activeIndex] ?? null;
    }

    private function presetCacheKey(int $charId): string
    {
        return "shadowwar_presets_$charId";
    }
}