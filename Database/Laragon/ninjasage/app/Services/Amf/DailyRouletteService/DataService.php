<?php

namespace App\Services\Amf\DailyRouletteService;

use App\Models\Character;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataService
{
    use ValidatesSession;

    /**
     * getData
     */
    public function getData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF DailyRoulette.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();
        $lastDate = $char->daily_roulette_date ? Carbon::parse($char->daily_roulette_date) : null;

        if (!$lastDate || $lastDate->diffInDays($today) > 0) {
            // New day
            if ($lastDate && $lastDate->diffInDays($today) == 1) {
                $char->daily_roulette_consecutive++;
                if ($char->daily_roulette_consecutive > 7) $char->daily_roulette_consecutive = 1;
            } else {
                $char->daily_roulette_consecutive = 1;
            }

            $char->daily_roulette_date = $today;
            $char->daily_roulette_count = 0; // Reset count
            $char->save();
        }

        // Logic: Can spin if count < max allowed
        // Free: 1 spin, Premium: 2 spins
        $user = User::find($char->user_id);
        $maxSpins = ($user->account_type == 1) ? 2 : 1;

        $canSpin = $char->daily_roulette_count < $maxSpins;

        return [
            'status' => 1,
            'can_spin' => $canSpin ? 1 : 0,
            'bonus' => $char->daily_roulette_consecutive // Frame for consecutive bonus
        ];
    }
}
