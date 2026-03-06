<?php

namespace App\Services\Amf\PvPService;

use App\Models\Character;

class PvpData
{
    public static function formatHairStyle(Character $char): string
    {
        $genderSuffix = ($char->gender == 0 ? '_0' : '_1');
        $hairStyle = $char->hair_style;
        if (is_numeric($hairStyle)) {
            return 'hair_' . str_pad((string)$hairStyle, 2, '0', STR_PAD_LEFT) . $genderSuffix;
        }
        if ($hairStyle) {
            return $hairStyle;
        }
        return 'hair_01' . $genderSuffix;
    }

    public static function formatFace(Character $char): string
    {
        $genderSuffix = ($char->gender == 0 ? '_0' : '_1');
        return 'face_01' . $genderSuffix;
    }

    public static function getDefaultSkill(Character $char): string
    {
        $elementSkillMap = [
            1 => 'skill_13',
            2 => 'skill_10',
            3 => 'skill_01',
            4 => 'skill_12',
            5 => 'skill_09',
        ];

        return $elementSkillMap[$char->element_1] ?? 'skill_01';
    }

    public static function getEquippedSkills(Character $char): array
    {
        $skills = $char->equipment_skills ?: self::getDefaultSkill($char);
        if (!is_string($skills)) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $skills)));
        return array_values($parts);
    }

    public static function buildLeaderboardEntry(Character $char): array
    {
        return [
            'id' => $char->id,
            'char_id' => $char->id,
            'name' => $char->name,
            'trophy' => $char->pvp_trophy ?? 0,
            'rank' => (int)$char->rank,
            'sets' => [
                'hair_style' => self::formatHairStyle($char),
                'face' => self::formatFace($char),
                'hair_color' => $char->hair_color ?? '0|0',
                'skin_color' => $char->skin_color ?? 'null|null',
            ],
        ];
    }

    public static function buildBattleListParticipant(Character $char, ?array $snapshot, ?int $trophyOverride = null): array
    {
        return [
            'id' => $snapshot['id'] ?? $char->id,
            'name' => $snapshot['name'] ?? $char->name,
            'rank' => $snapshot['rank'] ?? (int)$char->rank,
            'level' => $snapshot['level'] ?? (int)$char->level,
            'trophy' => $trophyOverride ?? ($snapshot['trophy'] ?? ($char->pvp_trophy ?? 0)),
        ];
    }

    public static function buildBattleDetailParticipant(Character $char, ?array $snapshot, ?int $trophyOverride = null): array
    {
        $skills = $snapshot['skills'] ?? self::getEquippedSkills($char);
        $talents = $snapshot['talents'] ?? [$char->talent_1, $char->talent_2, $char->talent_3];
        $set = $snapshot['set'] ?? [
            'clothing' => $char->equipment_clothing ?: 'set_01' . ($char->gender == 0 ? '_0' : '_1'),
            'accessory' => $char->equipment_accessory ?: 'accessory_01',
            'back_item' => $char->equipment_back ?: 'back_01',
            'weapon' => $char->equipment_weapon ?: 'wpn_01',
            'hairstyle' => self::formatHairStyle($char),
            'face' => self::formatFace($char),
            'hair_color' => $char->hair_color ?? '0|0',
            'skin_color' => $char->skin_color ?? 'null|null',
        ];

        return [
            'id' => $snapshot['id'] ?? $char->id,
            'name' => $snapshot['name'] ?? $char->name,
            'rank' => $snapshot['rank'] ?? (int)$char->rank,
            'level' => $snapshot['level'] ?? (int)$char->level,
            'trophy' => $trophyOverride ?? ($snapshot['trophy'] ?? ($char->pvp_trophy ?? 0)),
            'special_class' => $snapshot['special_class'] ?? ($char->class ?? 1),
            'skills' => $skills,
            'talents' => $talents,
            'set' => $set,
        ];
    }
}
