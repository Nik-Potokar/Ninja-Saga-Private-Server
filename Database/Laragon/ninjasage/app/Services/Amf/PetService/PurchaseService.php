<?php

namespace App\Services\Amf\PetService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    use ValidatesSession;

    /**
     * buyPet
     */
    public function buyPet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0];
        $sessionKey = $params[1];
        $petId = $params[2];

        Log::info("AMF PetService.buyPet: Char $charId Pet $petId");

        try {
            return DB::transaction(function () use ($charId, $petId) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $user = User::lockForUpdate()->find($char->user_id);
                if (!$user) return ['status' => 0, 'error' => 'User not found'];

                // Allow duplicates: Removed check for existing pet

                $petConfig = \App\Models\Pet::where('pet_id', $petId)->first();

                if (!$petConfig) return ['status' => 0, 'error' => 'Pet data not found!'];

                // Premium Check
                if ($petConfig->premium && $user->account_type == 0) {
                    return ['status' => 6]; // Triggers EmblemUpgrade popup
                }

                $priceGold = $petConfig->price_gold ?? 0;
                $priceTokens = $petConfig->price_tokens ?? 0;

                if ($char->gold < $priceGold || $user->tokens < $priceTokens) return ['status' => 3, 'result' => 'Not enough resources!'];

                $char->gold -= $priceGold;
                $char->save();

                if ($priceTokens > 0) {
                    $user->tokens -= $priceTokens;
                    $user->save();
                }

                CharacterPet::create([
                    'character_id' => $charId,
                    'pet_id' => $petId,
                    'name' => $petConfig->name,
                    'level' => 1,
                    'xp' => 0
                ]);

                return [
                    'status' => 1,
                    'error' => 0,
                    'data' => [
                        'character_gold' => $char->gold,
                        'account_tokens' => $user->tokens
                    ]
                ];
            });

        } catch (\Exception $e) {
            Log::error("Buy Pet Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * grantPet (Developer Tools)
     */
    public function grantPet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = $params[0];
        $petId = $params[2];

        Log::info("AMF PetService.grantPet: Char $charId Pet $petId");

        try {
            return DB::transaction(function () use ($charId, $petId) {
                $char = Character::lockForUpdate()->find($charId);
                if (!$char) return ['status' => 0, 'error' => 'Character not found'];

                $user = User::lockForUpdate()->find($char->user_id);
                if (!$user) return ['status' => 0, 'error' => 'User not found'];

                if (($user->account_type ?? 0) !== User::TYPE_ADMIN) {
                    return ['status' => 0, 'error' => 'Permission denied.'];
                }

                $petConfig = \App\Models\Pet::where('pet_id', $petId)->first();
                if (!$petConfig) return ['status' => 0, 'error' => 'Pet data not found!'];

                CharacterPet::create([
                    'character_id' => $charId,
                    'pet_id' => $petId,
                    'name' => $petConfig->name,
                    'level' => 1,
                    'xp' => 0
                ]);

                return [
                    'status' => 1,
                    'result' => 'Pet added.'
                ];
            });
        } catch (\Exception $e) {
            Log::error("Grant Pet Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }
}
