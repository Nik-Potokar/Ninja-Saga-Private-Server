<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterRecruit;
use App\Models\Npc;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class MissionService
{
    use ValidatesSession;

    /**
     * getMissionRoomData
     */
    public function getMissionRoomData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Get Mission Room Data: Char $charId");

        $recruits = CharacterRecruit::where('character_id', $charId)->get();
        $recruitData = [];

        foreach ($recruits as $r) {
            $isNpc = false;
            $recruitId = $r->recruit_id;

            // Check if it looks like NPC or exists in NPC table
            if (str_starts_with($recruitId, 'npc_')) {
                $isNpc = true;
            } else {
                if (Npc::where('npc_id', $recruitId)->exists()) {
                    $isNpc = true;
                } elseif (Npc::where('npc_id', 'npc_' . $recruitId)->exists()) {
                    $isNpc = true;
                    $recruitId = 'npc_' . $recruitId; // Normalize for client
                }
            }

            if ($isNpc) {
                $recruitData[] = [
                    'type' => 'npc',
                    'id' => ['recruiter_id' => $recruitId]
                ];
            } else {
                // It's a Character (Friend)
                $friendId = $recruitId;
                if (is_string($friendId) && str_starts_with($friendId, 'char_')) {
                    $friendId = substr($friendId, 5);
                }
                $friend = Character::with('user')->find($friendId);
                if ($friend) {
                    $genderSuffix = $friend->gender == 0 ? '_0' : '_1';
                    $hair = is_numeric($friend->hair_style)
                        ? 'hair_' . str_pad($friend->hair_style, 2, '0', STR_PAD_LEFT) . $genderSuffix
                        : ($friend->hair_style ?: 'hair_01' . $genderSuffix);

                    $recruitData[] = [
                        'type' => 'char',
                        'id' => $friend->id,
                        'info' => [
                            'name' => $friend->name,
                            'level' => $friend->level,
                            'rank' => $friend->rank,
                            'emblem' => ($friend->user->account_type ?? 0) == 1,
                            'element_1' => $friend->element_1,
                            'element_2' => $friend->element_2,
                            'element_3' => $friend->element_3,
                            'set' => [
                                'weapon' => $friend->equipment_weapon ?: 'wpn_01',
                                'back_item' => $friend->equipment_back ?: 'back_01',
                                'clothing' => $friend->equipment_clothing ?: 'set_01' . $genderSuffix,
                                'hairstyle' => $hair,
                                'accessory' => $friend->equipment_accessory ?: 'accessory_01',
                                'face' => 'face_01' . $genderSuffix,
                                'hair_color' => $friend->hair_color ?: '0|0',
                                'skin_color' => $friend->skin_color ?: '0|0',
                                'pet' => $friend->equipment_pet ?: 0,
                            ]
                        ]
                    ];
                }
            }
        }

        return [
            'status' => 1,
            'error' => 0,
            'recruit' => $recruitData,
            'daily' => []
        ];
    }
}
