<?php

namespace App\Services\Amf\TalentService;

use App\Models\Character;
use App\Models\CharacterTalent;

class TalentStringService
{
    public static function syncTalentString($charId)
    {
        $talents = CharacterTalent::where('character_id', $charId)->get();
        $parts = [];
        foreach ($talents as $t) {
            $parts[] = $t->skill_id . ":" . $t->level;
        }
        $str = implode(',', $parts);
        Character::where('id', $charId)->update(['talent_skills' => $str]);
    }
}
