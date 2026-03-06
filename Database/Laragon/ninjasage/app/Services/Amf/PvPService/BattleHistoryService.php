<?php

namespace App\Services\Amf\PvPService;

use App\Models\Character;
use App\Models\PvpBattle;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;
use App\Services\Amf\PvPService\PvpData;

class BattleHistoryService
{
    use ValidatesSession;

    public function getBattleActivity($charId, $sessionKey): array
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PvPService.getBattleActivity: Char $charId");

        $battles = PvpBattle::where('host_id', $charId)
            ->orWhere('enemy_id', $charId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        if ($battles->isEmpty()) {
            return [
                'status' => 1,
                'battles' => [],
            ];
        }

        $characterIds = $battles->pluck('host_id')
            ->merge($battles->pluck('enemy_id'))
            ->unique()
            ->values();

        $characters = Character::whereIn('id', $characterIds)->get()->keyBy('id');

        $data = [];
        foreach ($battles as $battle) {
            $host = $characters->get($battle->host_id);
            $enemy = $characters->get($battle->enemy_id);
            if (!$host || !$enemy) {
                continue;
            }

            $hostSnapshot = $battle->host_snapshot;
            $enemySnapshot = $battle->enemy_snapshot;
            $hostTrophy = $battle->host_trophy_after ?: ($host->pvp_trophy ?? 0);
            $enemyTrophy = $battle->enemy_trophy_after ?: ($enemy->pvp_trophy ?? 0);

            $isHost = $battle->host_id == $charId;
            $won = $isHost ? (bool)$battle->host_won : !(bool)$battle->host_won;
            $delta = (int)abs($battle->trophy_delta ?? 0);
            $trophyText = $delta > 0 ? (($won ? '+' : '-') . $delta) : '0';

            $data[] = [
                'id' => $battle->id,
                'type' => $battle->mode ? ucfirst($battle->mode) : '',
                'won' => $won,
                'trophy' => $trophyText,
                'date' => $battle->created_at ? $battle->created_at->format('d/m/Y H:i') : '',
                'host' => PvpData::buildBattleListParticipant($host, $hostSnapshot, $hostTrophy),
                'enemy' => PvpData::buildBattleListParticipant($enemy, $enemySnapshot, $enemyTrophy),
            ];
        }

        return [
            'status' => 1,
            'battles' => $data,
        ];
    }

    public function getDetailBattle($charId, $sessionKey, $battleId): array
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF PvPService.getDetailBattle: Char $charId Battle $battleId");

        $battle = PvpBattle::find($battleId);
        if (!$battle || ($battle->host_id != $charId && $battle->enemy_id != $charId)) {
            return [
                'status' => 0,
                'result' => 'Battle not found',
            ];
        }

        $host = Character::find($battle->host_id);
        $enemy = Character::find($battle->enemy_id);
        if (!$host || !$enemy) {
            return [
                'status' => 0,
                'result' => 'Battle data unavailable',
            ];
        }

        $isHost = $battle->host_id == $charId;
        $won = $isHost ? (bool)$battle->host_won : !(bool)$battle->host_won;
        $delta = (int)abs($battle->trophy_delta ?? 0);
        $trophyText = $delta > 0 ? (($won ? '+' : '-') . $delta) : '0';

        $hostTrophy = $battle->host_trophy_after ?: ($host->pvp_trophy ?? 0);
        $enemyTrophy = $battle->enemy_trophy_after ?: ($enemy->pvp_trophy ?? 0);

        return [
            'status' => 1,
            'won' => $won,
            'trophy' => $trophyText,
            'date' => $battle->created_at ? $battle->created_at->format('d/m/Y H:i') : '',
            'host' => PvpData::buildBattleDetailParticipant($host, $battle->host_snapshot, $hostTrophy),
            'enemy' => PvpData::buildBattleDetailParticipant($enemy, $battle->enemy_snapshot, $enemyTrophy),
        ];
    }
}
