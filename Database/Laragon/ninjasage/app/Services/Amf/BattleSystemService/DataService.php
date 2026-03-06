<?php

namespace App\Services\Amf\BattleSystemService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataService
{
    use ValidatesSession;

    private const MAX_ENERGY = 25;

    /**
     * getMissionSData
     */
    public function getMissionSData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        Log::info("AMF Get Mission S Data: Char $charId");

        $energyKey = "mission_s_energy_$charId";
        $stageKey = "mission_s_stage_$charId";

        $energy = Cache::get($energyKey);
        if ($energy === null) {
            $energy = self::MAX_ENERGY;
            Cache::put($energyKey, $energy, 86400);
        }

        $stage = Cache::get($stageKey, 1);
        if (!is_int($stage)) {
            $stage = (int)$stage;
        }

        $materials = $this->getMaterials($charId, ['material_899', 'material_900']);

        return [
            'status' => 1,
            'error' => 0,
            'stage' => $stage,
            'energy' => (int)$energy,
            'max_energy' => self::MAX_ENERGY,
            'materials' => $materials,
        ];
    }

    private function getMaterials(int $charId, array $materialIds): array
    {
        $materials = [];
        foreach ($materialIds as $materialId) {
            $materials[$materialId] = 0;
        }

        $items = CharacterItem::where('character_id', $charId)
            ->whereIn('item_id', $materialIds)
            ->get(['item_id', 'quantity']);

        foreach ($items as $item) {
            $materials[$item->item_id] = (int)$item->quantity;
        }

        return $materials;
    }
}
