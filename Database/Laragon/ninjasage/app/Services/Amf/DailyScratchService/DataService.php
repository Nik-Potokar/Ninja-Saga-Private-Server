<?php

namespace App\Services\Amf\DailyScratchService;

use App\Models\Character;
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

        Log::info("AMF DailyScratch.getData: Char $charId");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $today = Carbon::today();
        $lastDate = $char->daily_scratch_date
            ? Carbon::parse($char->daily_scratch_date)->startOfDay()
            : null;
        $daysDiff = $lastDate ? $lastDate->diffInDays($today) : null;

        if (!$lastDate || $daysDiff > 0) {
            // Reset for new day
            $char->daily_scratch_count = 0;

            // Consecutive Logic
            if ($lastDate && $daysDiff == 1) {
                $char->daily_scratch_consecutive++;
            } else {
                $char->daily_scratch_consecutive = 1;
            }

            $char->daily_scratch_date = $today;
            $char->save();
        }

        // Calculate available tickets
        // Free: 1 ticket. Premium: base 1 + consecutive day bonus
        $isPremium = $char->user && $char->user->account_type == 1;
        $maxTickets = $isPremium ? (1 + (int) $char->daily_scratch_consecutive) : 1;

        $tickets = max(0, $maxTickets - $char->daily_scratch_count);

        return [
            'status' => 1,
            'consecutive' => $char->daily_scratch_consecutive,
            'ticket' => $tickets
        ];
    }
}
