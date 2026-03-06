<?php

namespace App\Services\Amf\WelcomeLoginService;

use App\Models\GameConfig;

class RewardsCatalog
{
    private const DEFAULTS = [
        'gold_250000',
        'item_45:5',
        'essential_01:1',
        'essential_05:5',
        'essential_12:1',
        'tokens_500',
        'skill_2158',
    ];

    public static function all(): array
    {
        $rewards = GameConfig::get('welcome_login_rewards', self::DEFAULTS);
        $rewards = array_values((array) $rewards);

        // Always return exactly 7 slots; fall back to defaults for missing slots
        for ($i = 0; $i < 7; $i++) {
            if (empty($rewards[$i])) {
                $rewards[$i] = self::DEFAULTS[$i] ?? '';
            }
        }

        return array_slice($rewards, 0, 7);
    }
}