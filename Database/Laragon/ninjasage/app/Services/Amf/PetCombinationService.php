<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\Pet;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PetCombinationService
{
    use ValidatesSession;

    // Minimum pet level required to participate in a combination.
    private const REQUIRED_LEVEL = 30;

    // Minimum maturity points required to participate in a combination.
    private const REQUIRED_MP = 100;

    // Account tokens cost to activate the boost.
    private const BOOST_PRICE = 500;

    // Duration of the boost in seconds (3 hours).
    private const BOOST_DURATION = 10800;

    // Base success chance (percentage, 1–100).
    private const SUCCESS_RATE_BASE = 50;

    // Boosted success chance (percentage, 1–100).
    private const SUCCESS_RATE_BOOSTED = 80;

    // Item ID granted to the character on a successful combination.
    // Must correspond to a valid entry in the items table / client assets.
    private const REWARD_ITEM_ID = 'pet_combo_reward';

    // -------------------------------------------------------------------------
    // getData
    // -------------------------------------------------------------------------

    /**
     * Returns the current boost status for the Pet Combination panel.
     *
     * Client call:  PetCombination.getData([charId, sessionKey])
     * Response:     { status:1, boost: <remaining_seconds> }
     *   - boost == 0  → no active boost (show boost button)
     *   - boost  > 0  → seconds remaining (hide boost button, show countdown)
     */
    public function getData($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = (int) $params[0];

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found.'];
        }

        $boostRemaining = $this->getBoostRemaining($char);

        Log::info("AMF PetCombination.getData: Char $charId boost=$boostRemaining");

        // Return null (not 0) when no boost is active. The Flash client's
        // updateTimeLeft() checks `boost == null` to stop the countdown timer.
        // Returning 0 causes the timer to start immediately with a zero value,
        // and when the boost is later activated a second timer spawns, making
        // the visual countdown drain at double speed.
        return [
            'status' => 1,
            'boost'  => $boostRemaining > 0 ? $boostRemaining : null,
        ];
    }

    // -------------------------------------------------------------------------
    // combinePet
    // -------------------------------------------------------------------------

    /**
     * Executes a pet combination attempt.
     *
     * Client call:  PetCombination.combinePet([charId, sessionKey, leftPetId, rightPetId])
     *
     * On success (both pets deleted, reward item granted):
     *   { status:1, success:true, pets:"<item_id>" }
     *
     * On failure (both pets kept, MP halved):
     *   { status:1, success:false }
     */
    public function combinePet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId    = (int) $params[0];
        $leftPetId = (int) $params[2];
        $rightPetId = (int) $params[3];

        if ($leftPetId === $rightPetId) {
            return ['status' => 2, 'result' => 'Cannot combine a pet with itself.'];
        }

        return DB::transaction(function () use ($charId, $leftPetId, $rightPetId) {
            $char = Character::lockForUpdate()->find($charId);
            if (!$char) {
                return ['status' => 0, 'error' => 'Character not found.'];
            }

            // Load both pet instances (must belong to this character).
            $leftPet = CharacterPet::where('id', $leftPetId)
                ->where('character_id', $charId)
                ->lockForUpdate()
                ->first();

            $rightPet = CharacterPet::where('id', $rightPetId)
                ->where('character_id', $charId)
                ->lockForUpdate()
                ->first();

            if (!$leftPet || !$rightPet) {
                return ['status' => 2, 'result' => 'One or both pets were not found.'];
            }

            // Validate level requirements.
            if ($leftPet->level < self::REQUIRED_LEVEL || $rightPet->level < self::REQUIRED_LEVEL) {
                return ['status' => 2, 'result' => 'Both pets must be level ' . self::REQUIRED_LEVEL . ' or higher.'];
            }

            // Validate maturity point requirements.
            if ($leftPet->maturity_points < self::REQUIRED_MP || $rightPet->maturity_points < self::REQUIRED_MP) {
                return ['status' => 2, 'result' => 'Both pets need at least ' . self::REQUIRED_MP . ' Maturity Points (MP).'];
            }

            // Load master pet configs to get combine_gold values.
            $leftConfig  = Pet::where('pet_id', $leftPet->pet_id)->first();
            $rightConfig = Pet::where('pet_id', $rightPet->pet_id)->first();

            // Check combine_enabled on master data; default to allowed if column missing.
            if ($leftConfig && $leftConfig->combine_enabled === false) {
                return ['status' => 2, 'result' => 'The left pet cannot be used in combinations.'];
            }
            if ($rightConfig && $rightConfig->combine_enabled === false) {
                return ['status' => 2, 'result' => 'The right pet cannot be used in combinations.'];
            }

            $leftGold  = (int) ($leftConfig->combine_gold  ?? 5000);
            $rightGold = (int) ($rightConfig->combine_gold ?? 5000);
            $totalGold = $leftGold + $rightGold;

            if ($char->gold < $totalGold) {
                return [
                    'status' => 2,
                    'result' => "Not enough gold. Combination requires {$totalGold} gold.",
                ];
            }

            // Deduct gold cost.
            $char->gold -= $totalGold;
            $char->save();

            // Determine success using the active boost rate.
            $boosted  = $this->getBoostRemaining($char) > 0;
            $rate     = $boosted ? self::SUCCESS_RATE_BOOSTED : self::SUCCESS_RATE_BASE;
            $success  = rand(1, 100) <= $rate;

            Log::info("AMF PetCombination.combinePet: Char $charId Left=$leftPetId Right=$rightPetId Gold=$totalGold Boost=$boosted Success=$success");

            if ($success) {
                // Consume both pets.
                $leftPet->delete();
                $rightPet->delete();

                // Grant reward item (stack if already owned).
                $existing = CharacterItem::where('character_id', $charId)
                    ->where('item_id', self::REWARD_ITEM_ID)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity');
                } else {
                    CharacterItem::create([
                        'character_id' => $charId,
                        'item_id'      => self::REWARD_ITEM_ID,
                        'quantity'     => 1,
                        'category'     => 'material',
                    ]);
                }

                return [
                    'status'  => 1,
                    'success' => true,
                    'pets'    => self::REWARD_ITEM_ID,
                ];
            }

            // Failure: halve both pets' maturity points.
            $leftPet->maturity_points  = (int) floor($leftPet->maturity_points / 2);
            $rightPet->maturity_points = (int) floor($rightPet->maturity_points / 2);
            $leftPet->save();
            $rightPet->save();

            return [
                'status'  => 1,
                'success' => false,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // boostRate
    // -------------------------------------------------------------------------

    /**
     * Activates the combination rate boost for 3 hours, costing 500 account tokens.
     *
     * Client call:  PetCombination.boostRate([charId, sessionKey])
     * Response:     { status:1, result:"<message>", boost:<remaining_seconds> }
     */
    public function boostRate($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = (int) $params[0];

        $char = Character::with('user')->find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found.'];
        }

        // Reject if a boost is already active.
        if ($this->getBoostRemaining($char) > 0) {
            return ['status' => 2, 'result' => 'Combine rate boost is already active.'];
        }

        $user = $char->user;
        if (!$user) {
            return ['status' => 0, 'error' => 'User account not found.'];
        }

        if ($user->tokens < self::BOOST_PRICE) {
            return [
                'status' => 2,
                'result' => 'Not enough tokens. Boost requires ' . self::BOOST_PRICE . ' tokens.',
            ];
        }

        $expiresAt = time() + self::BOOST_DURATION;

        // Wrap both writes in a transaction so a partial failure never deducts
        // tokens without actually applying the boost.
        DB::transaction(function () use ($char, $user, $expiresAt) {
            $user->tokens -= self::BOOST_PRICE;
            $user->save();

            $char->pet_boost_expires_at = $expiresAt;
            $char->save();
        });

        Log::info("AMF PetCombination.boostRate: Char $charId boosted until $expiresAt");

        return [
            'status' => 1,
            'result' => 'Combine rate boosted for 3 hours! Good luck!',
            'boost'  => self::BOOST_DURATION,
        ];
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Returns remaining boost seconds for the given character (0 if no active boost).
     */
    private function getBoostRemaining(Character $char): int
    {
        if (!$char->pet_boost_expires_at) {
            return 0;
        }

        $remaining = (int) $char->pet_boost_expires_at - time();

        return max(0, $remaining);
    }
}