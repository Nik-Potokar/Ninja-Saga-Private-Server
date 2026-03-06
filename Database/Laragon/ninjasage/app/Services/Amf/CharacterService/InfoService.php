<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterFriend;
use App\Models\CharacterMail;
use App\Models\CharacterPet;
use App\Models\CharacterTalent;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\Pet;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class InfoService
{
    use ValidatesSession;

    /**
     * getInfo
     */
    public function getInfo($charId, $sessionKey, $targetId, $type)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Get Info: Char $charId Target $targetId Type $type");

        $char = Character::with('user')->find($targetId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $genderSuffix = ($char->gender == 0 ? '_0' : '_1');

        if (is_numeric($char->hair_style)) {
            $hairstyle = 'hair_' . str_pad($char->hair_style, 2, '0', STR_PAD_LEFT) . $genderSuffix;
        } else {
            $hairstyle = $char->hair_style ?: 'hair_01' . $genderSuffix;
        }

        $elementSkillMap = [
            1 => 'skill_13', 2 => 'skill_10', 3 => 'skill_01', 4 => 'skill_12', 5 => 'skill_09',
        ];

        $defaultSkill = $elementSkillMap[$char->element_1] ?? 'skill_01';

        $equippedSkills = $char->equipment_skills ?: $defaultSkill;

        // Populate Talent Skills String
        $talents = CharacterTalent::where('character_id', $targetId)->get();
        $talentParts = [];
        foreach ($talents as $t) {
            $talentParts[] = $t->skill_id . ":" . $t->level;
        }
        $talentSkillsStr = implode(',', $talentParts);

        $petData = ['pet' => 0];
        if ($char->equipment_pet) {
            $activePet = CharacterPet::where('character_id', $targetId)->where('id', $char->equipment_pet)->first();
            if ($activePet) {
                $petConfig = Pet::where('pet_id', $activePet->pet_id)->first();
                $petData = [
                    'pet' => $activePet->id,
                    'char_id' => $targetId,
                    'pet_id' => $activePet->id, // Instance ID
                    'pet_name' => $activePet->name ?: ($petConfig->name ?? 'Pet'),
                    'pet_xp' => $activePet->xp,
                    'pet_level' => $activePet->level,
                    'pet_skills' => $petConfig ? $petConfig->calculateSkillsString($activePet->level) : "1,1,1,1,1,1",
                    'pet_mp' => 0,
                    'pet_swf' => $petConfig->swf ?? ('pet_' . $activePet->pet_id)
                ];
            }
        }

        $charRank = (int)$char->rank;
        $reportedLevel = $char->level;

        // Fix for HUD buttons checking strict level equality
        if ($charRank == 1 && $char->level > 20) $reportedLevel = 20; // Genin -> Chunin Exam
        if ($charRank == 3 && $char->level > 40) $reportedLevel = 40; // Chunin -> Jounin Exam
        if ($charRank == 5 && $char->level > 60) $reportedLevel = 60; // Jounin -> Special Jounin Exam
        if ($charRank == 7 && $char->level > 80) $reportedLevel = 80; // SJ -> Ninja Tutor Exam

        $isFriend = CharacterFriend::where('character_id', $charId)
            ->where('friend_id', $targetId)
            ->exists();

        return [
            'status' => 1, 'error' => 0, 'friend' => $isFriend,
            'account_type' => $char->user->account_type,
            'emblem_duration' => -1, 'events' => (object)[],
            'has_unread_mails' => CharacterMail::where('character_id', $char->id)->where('viewed', false)->exists(),
            'character_data' => [
                'character_id' => $char->id, 'character_name' => $char->name,
                'character_level' => $reportedLevel, 'character_xp' => $char->xp,
                'character_gender' => $char->gender,
                'character_rank' => $charRank,
                'character_merit' => 0, 'character_prestige' => $char->prestige,
                'character_element_1' => $char->element_1, 'character_element_2' => $char->element_2,
                'character_element_3' => $char->element_3, 'character_talent_1' => $char->talent_1,
                'character_talent_2' => $char->talent_2, 'character_talent_3' => $char->talent_3,
                'character_gold' => $char->gold, 'character_tp' => $char->tp,
                'character_ss' => $char->ss, 'character_class' => $char->class, 'character_senjutsu' => $char->senjutsu_type, 'character_pvp_points' => 0
            ],
            'character_points' => [
                'atrrib_wind' => $char->point_wind, 'atrrib_fire' => $char->point_fire,
                'atrrib_lightning' => $char->point_lightning, 'atrrib_water' => $char->point_water,
                'atrrib_earth' => $char->point_earth, 'atrrib_free' => $char->point_free
            ],
            'character_slots' => ['weapons' => 100, 'back_items' => 100, 'accessories' => 100, 'hairstyles' => 100, 'clothing' => 100],
            'character_sets' => [
                'weapon' => $char->equipment_weapon ?: 'wpn_01',
                'back_item' => $char->equipment_back ?: 'back_01',
                'accessory' => $char->equipment_accessory ?: 'accessory_01',
                'hairstyle' => $hairstyle, 'clothing' => $char->equipment_clothing ?: 'set_01' . $genderSuffix,
                'skills' => $equippedSkills, 'senjutsu_skills' => $char->senjutsu_equipped_skills ?: '',
                'hair_color' => $char->hair_color ?? '0|0', 'skin_color' => $char->skin_color ?? 'null|null',
                'face' => 'face_01' . $genderSuffix, 'pet' => $char->equipment_pet ?: "", 'anims' => []
            ],
            'character_inventory' => [
                'char_talent_skills' => $talentSkillsStr, 'char_senjutsu_skills' => $char->senjutsu_skills ?: ""
            ],
            'features' => ['pvp'], 'recruiters' => [], 'recruit_data' => [], 'pet_data' => $petData,
            // EffectsManager.increaseFromPassiveEffects_Clan reads clan.training_hall with NO null
            // check during CLAN_MATCH battles — must always be a real object, never null.
            'clan' => $this->getClanBuildings($char->id)
        ];
    }

    private function getClanBuildings(int $charId): array
    {
        $defaults = ['ramen' => 0, 'hot_spring' => 0, 'temple' => 0, 'training_hall' => 0];
        $member   = ClanMember::where('character_id', $charId)->first();
        if (!$member) return $defaults;
        $clan = Clan::find($member->clan_id);
        if (!$clan) return $defaults;
        $b = $clan->buildings ?? $defaults;
        return [
            'ramen'         => (int)($b['ramen']         ?? 0),
            'hot_spring'    => (int)($b['hot_spring']    ?? 0),
            'temple'        => (int)($b['temple']        ?? 0),
            'training_hall' => (int)($b['training_hall'] ?? 0),
        ];
    }
}