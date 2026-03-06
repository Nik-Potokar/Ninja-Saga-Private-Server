<?php

namespace App\Services\Amf\SenjutsuService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class EquipService
{
    use ValidatesSession;

    /**
     * equipSkill
     * Params: [charId, sessionKey, skills]
     */
    public function equipSkill($charId, $sessionKey, $skills)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Senjutsu.equipSkill: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        $owned = $this->parseOwnedSkills($char->senjutsu_skills);
        $normalized = $this->normalizeSkills($skills, $owned);
        $equipped = implode(',', $normalized);

        $char->senjutsu_equipped_skills = $equipped;
        $char->save();

        return [
            'status' => 1,
            'skills' => $equipped,
        ];
    }

    private function normalizeSkills($skills, array $owned): array
    {
        if (is_string($skills)) {
            $skills = $skills === '' ? [] : explode(',', $skills);
        }

        if (!is_array($skills)) {
            return [];
        }

        $unique = [];
        foreach ($skills as $skill) {
            $skill = trim((string)$skill);
            if ($skill === '') {
                continue;
            }
            if (!in_array($skill, $owned, true)) {
                continue;
            }
            $unique[$skill] = true;
        }

        return array_slice(array_keys($unique), 0, 8);
    }

    private function parseOwnedSkills(?string $skillStr): array
    {
        if (!$skillStr) {
            return [];
        }

        $owned = [];
        $parts = explode(',', $skillStr);
        foreach ($parts as $part) {
            $baseId = trim(explode(':', $part, 2)[0]);
            if ($baseId !== '') {
                $owned[] = $baseId;
            }
        }

        return array_values(array_unique($owned));
    }
}
