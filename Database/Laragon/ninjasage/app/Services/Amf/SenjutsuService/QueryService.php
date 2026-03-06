<?php

namespace App\Services\Amf\SenjutsuService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class QueryService
{
    use ValidatesSession;

    /**
     * getSenjutsuSkills
     * Params: [charId, sessionKey]
     */
    public function getSenjutsuSkills($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Senjutsu.getSenjutsuSkills: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $owned = $this->parseSkills($char->senjutsu_skills);
        $typeMap = $this->getSenjutsuTypeMap();
        $data = [];
        foreach ($owned as $id => $lv) {
            $data[] = [
                'id' => $id,
                'level' => $lv,
                'type' => $typeMap[$id] ?? 'other',
            ];
        }

        return [
            'status' => 1,
            'data' => $data,
            'skills' => $data,
            'ss' => (int)$char->ss,
        ];
    }

    private function getSenjutsuTypeMap(): array
    {
        return Cache::remember('senjutsu_type_map', 3600, function () {
            $path = base_path('public/game_data/senjutsu.json');
            if (!File::exists($path)) {
                return [];
            }

            $list = json_decode(File::get($path), true);
            if (!is_array($list)) {
                return [];
            }

            $map = [];
            foreach ($list as $entry) {
                if (!is_array($entry) || !isset($entry['id'], $entry['type'])) {
                    continue;
                }

                $id = (string)$entry['id'];
                $baseId = explode(':', $id, 2)[0];
                if (!isset($map[$baseId])) {
                    $map[$baseId] = (string)$entry['type'];
                }
            }

            return $map;
        });
    }

    private function parseSkills($skillStr)
    {
        if (!$skillStr) return [];
        $skills = [];
        $parts = explode(',', $skillStr);
        foreach ($parts as $p) {
            if (strpos($p, ':') !== false) {
                list($id, $lv) = explode(':', $p);
                $skills[$id] = (int)$lv;
            } else {
                $skills[$p] = 1;
            }
        }
        return $skills;
    }
}
