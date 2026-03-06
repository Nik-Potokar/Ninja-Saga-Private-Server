<?php

namespace App\Filament\Resources\GameEvents\Pages;

use App\Filament\Resources\GameEvents\GameEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGameEvent extends CreateRecord
{
    protected static string $resource = GameEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('mh_boss_id', $data) && $data['mh_boss_id'] !== null && $data['mh_boss_id'] !== '') {
            if (!is_array($data['data'])) {
                $data['data'] = [];
            }
            $data['data']['boss_id'] = $data['mh_boss_id'];
        }

        unset($data['mh_boss_id']);

        if (in_array($data['panel'] ?? '', config('game_events.gacha_panels', []))) {
            $data['data'] = self::buildGachaData($data);
        }

        unset(
            $data['gacha_weight_top'],
            $data['gacha_weight_mid'],
            $data['gacha_weight_common'],
            $data['gacha_draws'],
            $data['gacha_pool_top'],
            $data['gacha_pool_mid'],
            $data['gacha_pool_common'],
        );

        return $data;
    }

    private static function buildGachaData(array $data): array
    {
        $poolData = [];

        $poolData['pool_weights'] = [
            (int) ($data['gacha_weight_top']    ?? 5),
            (int) ($data['gacha_weight_mid']    ?? 25),
            (int) ($data['gacha_weight_common'] ?? 70),
        ];

        $draws = [];
        foreach ($data['gacha_draws'] ?? [] as $row) {
            $key = $row['draw_key'] ?? null;
            if (!$key) {
                continue;
            }
            $entry = ['qty' => (int) ($row['qty'] ?? 1)];
            if (isset($row['coin_cost'])  && $row['coin_cost']  !== '' && $row['coin_cost']  !== null) {
                $entry['coin_cost']  = (int) $row['coin_cost'];
            }
            if (isset($row['token_cost']) && $row['token_cost'] !== '' && $row['token_cost'] !== null) {
                $entry['token_cost'] = (int) $row['token_cost'];
            }
            $draws[$key] = $entry;
        }
        $poolData['draws'] = $draws;

        $splitLines = fn (string $raw): array => array_values(
            array_filter(array_map('trim', explode("\n", $raw)))
        );

        $poolData['pool'] = [
            'top'    => $splitLines($data['gacha_pool_top']    ?? ''),
            'mid'    => $splitLines($data['gacha_pool_mid']    ?? ''),
            'common' => $splitLines($data['gacha_pool_common'] ?? ''),
        ];

        return $poolData;
    }
}