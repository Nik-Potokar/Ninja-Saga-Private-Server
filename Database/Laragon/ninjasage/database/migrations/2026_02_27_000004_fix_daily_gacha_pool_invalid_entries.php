<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixes the DailyGacha GameEvent pool:
 * - Removes 'gold_' (broken: no amount, grants 0 gold, shows broken icon)
 *   and replaces it with 'gold_50000' in the common pool.
 *
 * The prize list's "Biggest Prize" section (top tier) only has 2 UI slots.
 * The service's getRewardList already caps the top array to 2 items, so
 * no pool change is needed there — but keeping top to a small curated set
 * is still good practice for fresh installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $event = DB::table('game_events')->where('panel', 'DailyGacha')->first();
        if (!$event || empty($event->data)) {
            return;
        }

        $data = json_decode($event->data, true);
        if (!is_array($data)) {
            return;
        }

        $changed = false;

        // Fix common pool: replace 'gold_' with 'gold_50000'
        if (isset($data['pool']['common']) && is_array($data['pool']['common'])) {
            $fixed = array_map(
                fn($id) => $id === 'gold_' ? 'gold_50000' : $id,
                $data['pool']['common']
            );
            if ($fixed !== $data['pool']['common']) {
                $data['pool']['common'] = $fixed;
                $changed = true;
            }
        }

        // Fix mid pool: replace 'tokens_' with 'tokens_500'
        if (isset($data['pool']['mid']) && is_array($data['pool']['mid'])) {
            $fixed = array_map(
                fn($id) => $id === 'tokens_' ? 'tokens_500' : $id,
                $data['pool']['mid']
            );
            if ($fixed !== $data['pool']['mid']) {
                $data['pool']['mid'] = $fixed;
                $changed = true;
            }
        }

        if ($changed) {
            DB::table('game_events')
                ->where('panel', 'DailyGacha')
                ->update(['data' => json_encode($data), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Intentionally left blank — no need to restore broken entries
    }
};
