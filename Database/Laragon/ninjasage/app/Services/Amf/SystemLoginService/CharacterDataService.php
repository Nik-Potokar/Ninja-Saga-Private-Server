<?php

namespace App\Services\Amf\SystemLoginService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterMail;
use App\Models\CharacterPet;
use App\Models\CharacterRecruit;
use App\Models\CharacterSkill;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\GameConfig;
use App\Models\MysteriousMarket;
use App\Models\Pet;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class CharacterDataService
{
    use ValidatesSession;

    /**
     * getCharacterData
     */
    public function getCharacterData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Get Character Data: ID $charId");

        $char = Character::with('user')->find($charId);

        if (!$char) {
            return [
                'status' => 0,
                'error' => 'Character not found'
            ];
        }

        // Fix Talent Slots if missing
        $this->fixTalentSlots($char);

        $genderSuffix = ($char->gender == 0 ? '_0' : '_1');

        $weapon = $char->equipment_weapon ?: 'wpn_01';
        $backItem = $char->equipment_back ?: 'back_01';
        $accessory = $char->equipment_accessory ?: 'accessory_01';
        $clothing = $char->equipment_clothing ?: 'set_01' . $genderSuffix;

        if (is_numeric($char->hair_style)) {
            $hairstyle = 'hair_' . str_pad($char->hair_style, 2, '0', STR_PAD_LEFT) . $genderSuffix;
        } else {
            $hairstyle = $char->hair_style ?: 'hair_01' . $genderSuffix;
        }

        // Elemental Skill Mapping for Fallback
        $elementSkillMap = [
            1 => 'skill_13', // Wind
            2 => 'skill_10', // Fire
            3 => 'skill_01', // Lightning
            4 => 'skill_12', // Earth
            5 => 'skill_09', // Water
        ];

        $defaultSkill = $elementSkillMap[$char->element_1] ?? 'skill_01';
        $equippedSkills = $char->equipment_skills ?: $defaultSkill;

        // Fetch Equipped Pet
        $petData = [];
        if ($char->equipment_pet) {
            $activePet = CharacterPet::where('character_id', $charId)
                ->where('id', $char->equipment_pet)
                ->first();

            if ($activePet) {
                $petConfig = Pet::where('pet_id', $activePet->pet_id)->first();

                $petData = [
                    'pet' => $activePet->id,
                    'char_id' => $charId,
                    'pet_id' => $activePet->id, // Instance ID
                    'pet_name' => $activePet->name ?: ($petConfig->name ?? 'Pet'),
                    'pet_xp' => $activePet->xp,
                    'pet_level' => $activePet->level,
                    'pet_skills' => $petConfig ? $petConfig->calculateSkillsString($activePet->level) : "1,1,1,1,1,1",
                    'pet_mp' => 0,
                    'pet_swf' => $petConfig->swf ?? ('pet_' . $activePet->pet_id) // Type ID
                ];
            }
        }

        // Fetch Recruits
        $recruits = CharacterRecruit::where('character_id', $charId)->get(['recruit_id']);
        $recruitersList = [];
        foreach ($recruits as $r) {
            $recruitersList[] = ['recruited_char_id' => $r->recruit_id];
        }

        $hash = "";

        if (!empty($recruitersList)) {

            $hash = hash('sha256', $recruitersList[0]['recruited_char_id']);

        }

        $recruiters = [$recruitersList, $hash];

        $rankMap = [
            1 => 1, // Genin
            3 => 3, // Chunin
            5 => 5, // Jounin
            7 => 7, // Special Jounin
            8 => 8, // Sannin
            10 => 10, // Kage
        ];

        $charRank = (int)$char->rank;
        $reportedLevel = $char->level;

        // Fix for HUD buttons checking strict level equality
        if ($charRank == 1 && $char->level > 20) $reportedLevel = 20; // Genin -> Chunin Exam
        if ($charRank == 3 && $char->level > 40) $reportedLevel = 40; // Chunin -> Jounin Exam
        if ($charRank == 5 && $char->level > 60) $reportedLevel = 60; // Jounin -> Special Jounin Exam
        if ($charRank == 7 && $char->level > 80) $reportedLevel = 80; // SJ -> Ninja Tutor Exam

        $petCount = CharacterPet::where('character_id', $charId)->count();

        return [
            'status' => 1,
            'error' => 0,
            'announcements' => $this->getAnnouncements(),
            'account_type' => $char->user->account_type,
            'tokens' => $char->tokens,
            'emblem_duration' => -1,
            'events' => $this->getActiveEventFlags(),
            'has_unread_mails' => CharacterMail::where('character_id', $char->id)->where('viewed', false)->exists(),
            'pet_count' => $petCount,

            'character_data' => [
                'character_id' => $char->id,
                'character_name' => $char->name,
                'character_level' => $reportedLevel,
                'character_xp' => $char->xp,
                'character_gender' => $char->gender,
                'character_rank' => $charRank,
                'character_merit' => 0,
                'character_prestige' => $char->prestige,
                'character_element_1' => $char->element_1,
                'character_element_2' => $char->element_2,
                'character_element_3' => $char->element_3,
                'character_talent_1' => $char->talent_1,
                'character_talent_2' => $char->talent_2,
                'character_talent_3' => $char->talent_3,
                'character_gold' => $char->gold,
                'character_tp' => $char->tp,
                'character_ss' => $char->ss,
                'character_class' => $char->class,
                'character_senjutsu' => $char->senjutsu_type,
                'character_pvp_points' => (int)$char->pvp_points
            ],

            'character_points' => [
                'atrrib_wind' => $char->point_wind,
                'atrrib_fire' => $char->point_fire,
                'atrrib_lightning' => $char->point_lightning,
                'atrrib_water' => $char->point_water,
                'atrrib_earth' => $char->point_earth,
                'atrrib_free' => $char->point_free
            ],

            'character_slots' => [
                'weapons' => 100,
                'back_items' => 100,
                'accessories' => 100,
                'hairstyles' => 100,
                'clothing' => 100
            ],

            'character_sets' => [
                'weapon' => $weapon,
                'back_item' => $backItem,
                'accessory' => $accessory,
                'hairstyle' => $hairstyle,
                'clothing' => $clothing,
                'skills' => $equippedSkills,
                'senjutsu_skills' => $char->senjutsu_equipped_skills ?: '',
                'hair_color' => $char->hair_color ?? '0|0',
                'skin_color' => $char->skin_color ?? 'null|null',
                'face' => 'face_01' . $genderSuffix,
                'pet' => $char->equipment_pet ?: "",
                'anims' => []
            ],

            'character_inventory' => [
                'char_weapons' => $this->getItemsString($charId, 'weapon'),
                'char_back_items' => $this->getItemsString($charId, 'back'),
                'char_accessories' => $this->getItemsString($charId, 'accessory'),
                'char_sets' => $this->getItemsString($charId, 'set'),
                'char_hairs' => $this->getItemsString($charId, 'hair'),
                'char_skills' => $this->getSkillsString($charId),
                'char_talent_skills' => $char->talent_skills ?: '',
                'char_senjutsu_skills' => $char->senjutsu_skills ?: '',
                'char_materials' => $this->getItemsString($charId, 'material'),
                'char_items' => $this->getItemsString($charId, 'item'),
                'char_essentials' => $this->getItemsString($charId, 'essential'),
                'char_animations' => $this->getAnimationsString($charId)
            ],

            'features' => ['pvp'],
            'recruiters' => [],//$recruiters,
            'recruit_data' => [],
            'pet_data' => $petData,
            // EffectsManager.increaseFromPassiveEffects_Clan reads clan.training_hall with NO null
            // check during CLAN_MATCH battles — must always be a real object, never null.
            'clan' => $this->getClanBuildings($charId)
        ];
    }

    private function getItemsString($charId, $category)
    {
        $items = CharacterItem::where('character_id', $charId)->where('category', $category)->get();
        if ($items->isEmpty()) return "";
        $parts = [];
        foreach ($items as $item) {
            $parts[] = ($category == 'hair') ? $item->item_id : $item->item_id . ":" . $item->quantity;
        }
        return implode(',', $parts);
    }

    private function getAnnouncements(): string
    {
        $cfg = GameConfig::get('announcements', []);
        if (empty($cfg['enabled'])) {
            return '';
        }
        return (string)($cfg['text'] ?? '');
    }

    private function getSkillsString($charId)
    {
        $skills = CharacterSkill::where('character_id', $charId)->get();
        if ($skills->isEmpty()) return "";
        return implode(',', $skills->pluck('skill_id')->toArray());
    }

    private function getAnimationsString($charId)
    {
        $animations = CharacterItem::where('character_id', $charId)
            ->where('category', 'animation')
            ->get();
        if ($animations->isEmpty()) return "";
        return implode(',', $animations->pluck('item_id')->toArray());
    }

    private function fixTalentSlots(Character $char)
    {
        // If slots are already filled, we might not need to do anything,
        // but the user said "if not set we need automatically fix".
        // Let's check if we have unassigned talents that *could* be assigned.

        $hasChanges = false;

        // 1. Get owned talent skills
        // We can parse talent_skills string or query CharacterTalent table.
        // Table is more reliable.
        $ownedSkills = \App\Models\CharacterTalent::where('character_id', $char->id)->get();
        if ($ownedSkills->isEmpty()) return;

        // 2. Identify owned Talent IDs and their Types
        // Map Skill ID -> Talent ID using GameConfig 'talent_description'
        $talentDesc = \App\Models\GameConfig::get('talent_description');
        $ownedTalentIds = [];

        if ($talentDesc && is_array($talentDesc)) {
            foreach ($ownedSkills as $tSkill) {
                // Find which talent tree this skill belongs to
                foreach ($talentDesc as $key => $info) {
                    if (isset($info['talent_skill_id']) && $info['talent_skill_id'] === $tSkill->skill_id) {
                        // Extract talent ID from key: talent_{id}_skill_{order}
                        if (preg_match('/talent_(.*)_skill_\d+/', $key, $matches)) {
                            $tid = $matches[1];
                            $ownedTalentIds[$tid] = true; // Use array keys for uniqueness
                        }
                        break; // Found the talent for this skill
                    }
                }
            }
        }

        $ownedTalentIds = array_keys($ownedTalentIds);
        if (empty($ownedTalentIds)) return;

        // 3. Classify Talents (Extreme vs Secret)
        $extremeTalents = ['eightext', 'saint', 'sm', 'eom', 'de', 'dp', 'insect', 'orochi'];
        $secretTalents = ['crystal', 'lava', 'ice', 'wood', 'sound', 'shadow', 'iron', 'dm', 'lm', 'eoc'];

        $myExtreme = [];
        $mySecret = [];

        foreach ($ownedTalentIds as $tid) {
            if (in_array($tid, $extremeTalents)) {
                $myExtreme[] = $tid;
            } elseif (in_array($tid, $secretTalents)) {
                $mySecret[] = $tid;
            }
        }

        // 4. Assign to Slots if empty
        // Talent 1: Extreme
        if ((!$char->talent_1 || $char->talent_1 === '0') && !empty($myExtreme)) {
            $char->talent_1 = $myExtreme[0];
            $hasChanges = true;
        }

        // Talent 2: Secret 1
        if ((!$char->talent_2 || $char->talent_2 === '0') && !empty($mySecret)) {
            $char->talent_2 = $mySecret[0];
            $hasChanges = true;
        }

        // Talent 3: Secret 2 (Rank >= 7 Special Jounin)
        // Check if we have a second secret talent
        if ($char->rank >= 7 && count($mySecret) > 1) {
            if (!$char->talent_3 || $char->talent_3 === '0') {
                // Assign the one that is NOT talent_2
                if ($mySecret[0] !== $char->talent_2) {
                    $char->talent_3 = $mySecret[0];
                } else {
                    $char->talent_3 = $mySecret[1];
                }
                $hasChanges = true;
            }
        } elseif ($char->rank < 7) {
            // Ensure slot 3 is empty if rank is too low (optional, but good for consistency)
            if ($char->talent_3 && $char->talent_3 !== '0') {
                // Maybe don't clear it automatically to avoid data loss if rank bugged?
                // But user requested "fix". Let's stick to populating empty ones.
            }
        }

        if ($hasChanges) {
            $char->save();
        }
    }

    /**
     * Returns an array of active event/feature flags sent to the client as Character.hide_event.
     * The HUD checks for specific strings in this array to show side-panel buttons:
     *   "mysterious-market" → Limited Store button
     *   "special-deals"     → Special Deals button
     */
    private function getActiveEventFlags(): array
    {
        $flags = [];

        // Limited Store: active only when a MysteriousMarket record is active and not expired
        $market = MysteriousMarket::where('active', true)->first();
        if ($market && $market->secondsRemaining() > 0) {
            $flags[] = 'mysterious-market';
        }

        // Special Deals: toggled via admin Game Settings
        $gameSettings = GameConfig::get('game_settings', []);
        if (!empty($gameSettings['special_deals_visible'])) {
            $flags[] = 'special-deals';
        }

        return $flags;
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