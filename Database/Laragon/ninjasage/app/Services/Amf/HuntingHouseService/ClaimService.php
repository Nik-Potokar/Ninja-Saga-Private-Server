<?php

namespace App\Services\Amf\HuntingHouseService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ClaimService
{
    use ValidatesSession;

    /**
     * dailyClaim
     */
    public function dailyClaim($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF HuntingHouse.dailyClaim: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        $today = Carbon::today()->toDateString();

        if ($char->hunting_house_date === $today) {
            return ['status' => 2, 'result' => 'Already claimed today!'];
        }

        $settings      = \App\Models\GameConfig::get('hunting_house_settings', []);
        $freeAmount    = (int) ($settings['daily_claim_free']    ?? 5);
        $premiumAmount = (int) ($settings['daily_claim_premium'] ?? 10);
        $amount = ($user->account_type == 1) ? $premiumAmount : $freeAmount;

        $char->hunting_house_date = $today;
        $char->save();

        $inv = CharacterItem::firstOrCreate(
            ['character_id' => $charId, 'item_id' => 'material_509'],
            ['quantity' => 0, 'category' => 'material']
        );
        $inv->quantity += $amount;
        $inv->save();

        return [
            'status' => 1,
            'material' => (int)$inv->quantity
        ];
    }
}