<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterMysteriousMarket;
use App\Models\CharacterSkill;
use App\Models\MysteriousMarket;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MysteriousMarketService
{
    use ValidatesSession;

    // -------------------------------------------------------------------------
    // getPackageData
    // Client params: [charId, sessionKey]
    // Returns: market config + per-character refresh state + current items
    // -------------------------------------------------------------------------
    public function getPackageData($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MysteriousMarket.getPackageData: Char $charId");

        $market = MysteriousMarket::where('active', true)->first();

        if (!$market || $market->secondsRemaining() <= 0) {
            // Market is closed – return empty items so client shows "closed" message
            return [
                'status'        => 1,
                'end_time'      => 0,
                'discounts'     => '0',
                'refresh_cost'  => 0,
                'refresh_count' => 0,
                'items'         => [],
            ];
        }

        $charState = $this->getOrCreateCharState((int) $charId, $market);

        // Use custom items if the player has refreshed, otherwise global defaults
        $items = $charState->custom_items ?? $market->items ?? [];

        return [
            'status'        => 1,
            'end_time'      => $market->secondsRemaining(),
            'discounts'     => $market->discount,
            'refresh_cost'  => $market->refresh_cost,
            'refresh_count' => $charState->refresh_count,
            'items'         => array_values($items),
        ];
    }

    // -------------------------------------------------------------------------
    // buyPackage
    // Client params: [charId, sessionKey, skillId]
    // -------------------------------------------------------------------------
    public function buyPackage($charId, $sessionKey, $skillId)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MysteriousMarket.buyPackage: Char $charId Skill $skillId");

        $market = MysteriousMarket::where('active', true)->first();
        if (!$market || $market->secondsRemaining() <= 0) {
            return ['status' => 2, 'result' => 'The Mysterious Market is not currently open.'];
        }

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $charState = $this->getOrCreateCharState((int) $charId, $market);
        $items     = $charState->custom_items ?? $market->items ?? [];

        // Find the purchased item's index in the current item list
        $itemIndex = null;
        $itemEntry  = null;
        foreach ($items as $idx => $entry) {
            if (($entry['code'] ?? '') === $skillId) {
                $itemIndex = $idx;
                $itemEntry  = $entry;
                break;
            }
        }

        if ($itemIndex === null) {
            return ['status' => 2, 'result' => 'That skill is not available in the current market.'];
        }

        // Check if already owned
        $alreadyOwned = CharacterSkill::where('character_id', $charId)
            ->where('skill_id', $skillId)
            ->exists();
        if ($alreadyOwned) {
            return ['status' => 2, 'result' => 'You already own this skill.'];
        }

        // Sequential purchase requirement: must own the previous item (if any)
        if ($itemIndex > 0) {
            $prevCode = $items[$itemIndex - 1]['code'] ?? null;
            if ($prevCode) {
                $ownsPrev = CharacterSkill::where('character_id', $charId)
                    ->where('skill_id', $prevCode)
                    ->exists();
                if (!$ownsPrev) {
                    return ['status' => 2, 'result' => 'You must purchase the previous skill first.'];
                }
            }
        }

        // Determine price based on account type
        // account_type == 1 (limited) → buyBtn_2 → prices[1]
        // account_type != 1           → buyBtn_1 → prices[0]
        $prices = $itemEntry['prices'] ?? [0, 0];
        $price  = ($user->account_type == 1) ? (int) ($prices[1] ?? 0) : (int) ($prices[0] ?? 0);

        if ($user->tokens < $price) {
            return ['status' => 2, 'result' => 'Not enough tokens.'];
        }

        try {
            return DB::transaction(function () use ($user, $char, $charId, $skillId, $price, $itemIndex, $items) {
                $user->tokens -= $price;
                $user->save();

                // Add new skill
                CharacterSkill::firstOrCreate([
                    'character_id' => $charId,
                    'skill_id'     => $skillId,
                ]);

                // Remove the previous tier skill when buying index > 0 (upgrade pattern)
                if ($itemIndex > 0) {
                    $prevCode = $items[$itemIndex - 1]['code'] ?? null;
                    if ($prevCode) {
                        CharacterSkill::where('character_id', $charId)
                            ->where('skill_id', $prevCode)
                            ->delete();
                    }
                }

                Log::info("MysteriousMarket: Char $charId bought $skillId for $price tokens");

                return ['status' => 1];
            });
        } catch (\Exception $e) {
            Log::error("MysteriousMarket buyPackage error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Transaction failed'];
        }
    }

    // -------------------------------------------------------------------------
    // getAllPackagesList
    // Client params: [charId, sessionKey]
    // Returns: all packages with ownership flag
    // -------------------------------------------------------------------------
    public function getAllPackagesList($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MysteriousMarket.getAllPackagesList: Char $charId");

        $market = MysteriousMarket::where('active', true)->first();
        if (!$market) {
            return ['status' => 2, 'result' => 'No active market found.'];
        }

        $allPackages = $market->all_packages ?? [];

        // Fetch all skill IDs owned by this character in one query
        $ownedSkills = CharacterSkill::where('character_id', $charId)
            ->pluck('skill_id')
            ->flip()
            ->all();

        $packages = [];
        foreach ($allPackages as $entry) {
            $code       = $entry['code'] ?? '';
            $packages[] = [
                'advanced_skill' => $code,
                'owned'          => isset($ownedSkills[$code]),
            ];
        }

        return [
            'status'   => 1,
            'packages' => $packages,
        ];
    }

    // -------------------------------------------------------------------------
    // refreshPackage
    // Client params: [charId, sessionKey]
    // Deducts refresh_cost tokens, picks new random items from all_packages
    // -------------------------------------------------------------------------
    public function refreshPackage($charId, $sessionKey)
    {
        $guard = $this->guardCharacterSession((int) $charId, $sessionKey);
        if ($guard) return $guard;

        Log::info("AMF MysteriousMarket.refreshPackage: Char $charId");

        $market = MysteriousMarket::where('active', true)->first();
        if (!$market || $market->secondsRemaining() <= 0) {
            return ['status' => 2, 'result' => 'The Mysterious Market is not currently open.'];
        }

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found'];

        $charState = $this->getOrCreateCharState((int) $charId, $market);

        if ($charState->refresh_count <= 0) {
            return ['status' => 2, 'result' => 'You have no refreshes remaining.'];
        }

        if ($user->tokens < $market->refresh_cost) {
            return ['status' => 2, 'result' => 'Not enough tokens to refresh.'];
        }

        $allPackages = $market->all_packages ?? [];
        if (empty($allPackages)) {
            return ['status' => 2, 'result' => 'No packages available to refresh.'];
        }

        // How many items are in the default display
        $displayCount = count($market->items ?? []);
        $displayCount = max(1, min(4, $displayCount));

        // Fetch skills the character already owns
        $ownedSkills = CharacterSkill::where('character_id', $charId)
            ->pluck('skill_id')
            ->flip()
            ->all();

        // Filter to unowned packages and shuffle
        $available = array_filter($allPackages, fn($e) => !isset($ownedSkills[$e['code'] ?? '']));
        $available = array_values($available);
        shuffle($available);

        $newItems = array_slice($available, 0, $displayCount);

        try {
            return DB::transaction(function () use ($user, $charState, $market, $newItems) {
                $user->tokens -= $market->refresh_cost;
                $user->save();

                $charState->refresh_count -= 1;
                $charState->custom_items   = $newItems;
                $charState->save();

                return ['status' => 1];
            });
        } catch (\Exception $e) {
            Log::error("MysteriousMarket refreshPackage error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Transaction failed'];
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getOrCreateCharState(int $charId, MysteriousMarket $market): CharacterMysteriousMarket
    {
        return CharacterMysteriousMarket::firstOrCreate(
            ['character_id' => $charId, 'market_id' => $market->id],
            ['refresh_count' => $market->refresh_max, 'custom_items' => null]
        );
    }
}
