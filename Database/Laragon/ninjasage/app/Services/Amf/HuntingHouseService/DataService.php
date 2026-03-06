<?php

namespace App\Services\Amf\HuntingHouseService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataService
{
    use ValidatesSession;

    /**
     * getData
     * Params: [charId, sessionKey]
     */
    public function getData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF HuntingHouse.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today()->toDateString();

        // Handle daily material claim status
        $dailyClaimed = false;
        if ($char->hunting_house_date === $today) {
            $dailyClaimed = true;
        }

        // Kari Badge count
        $material = CharacterItem::where('character_id', $charId)
            ->where('item_id', 'material_509')
            ->value('quantity') ?: 0;

        // Each zone has EITHER easyBoss (1 boss) OR hardBoss (2 bosses), never both.
        // The Flash client's setWorldMap() only handles one per zone — if both are non-null
        // neither branch runs and level text stays at the Flash-IDE default "99~99".
        $zones = [
            ['easyBoss' => ['ene_81'],           'hardBoss' => null],
            ['easyBoss' => null,                  'hardBoss' => ['ene_82', 'ene_83']],
            ['easyBoss' => ['ene_84'],           'hardBoss' => null],
            ['easyBoss' => null,                  'hardBoss' => ['ene_120', 'ene_155']],
            ['easyBoss' => null,                  'hardBoss' => ['ene_106', 'ene_101']],
        ];

        // All boss rewards display material_509 (Kari Badge) because that is the only
        // hunting material with a working client-side SWF icon. The actual server-side
        // item grants come from HuntService::ZONE_REWARDS and are tracked separately.
        $bosses = [
            'ene_81'  => ['name' => 'Ginkotsu',          'description' => 'A mechanical terror.',  'rewards' => ['material_509']],
            'ene_82'  => ['name' => 'Shikigami Yanki',    'description' => 'A paper spirit.',       'rewards' => ['material_509']],
            'ene_83'  => ['name' => 'Gedo Sessho Seki',   'description' => 'The killing stone.',    'rewards' => ['material_509']],
            'ene_84'  => ['name' => 'Tengu - Fire',       'description' => 'Fire Tengu.',           'rewards' => ['material_509']],
            'ene_120' => ['name' => 'Battle Turtle',      'description' => 'Giant Turtle.',         'rewards' => ['material_509']],
            'ene_155' => ['name' => 'Soul General Mutoh', 'description' => 'Undead General.',       'rewards' => ['material_509']],
            'ene_106' => ['name' => 'Ape King',           'description' => 'King of Apes.',         'rewards' => ['material_509']],
            'ene_101' => ['name' => 'Yamata no Orochi',   'description' => 'Eight-headed serpent.', 'rewards' => ['material_509']],
        ];

        return [
            'status' => 1,
            'material' => (int)$material,
            'daily_claim' => $dailyClaimed,
            'zones' => $zones,
            'bosses' => $bosses
        ];
    }
}