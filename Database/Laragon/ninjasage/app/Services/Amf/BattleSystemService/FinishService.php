<?php

namespace App\Services\Amf\BattleSystemService;

use App\Models\Character;
use App\Models\CharacterPet;
use App\Models\CharacterRecruit;
use App\Models\XP;
use App\Services\Amf\SessionValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinishService
{
    /**
     * finishMission
     */
    public function finishMission($charId, $missionId, $token, $hash, $score, $sessionKey, $battleData, $unknown)
    {
        Log::info("AMF Finish Mission Attempt: Char $charId Mission $missionId Token $token");

        $guard = SessionValidator::validateCharacter((int)$charId, $sessionKey);
        if ($guard) return $guard;

        $cachedBattle = Cache::get("battle_token_$charId");

        if (!$cachedBattle) {
            Log::warning("No cached battle found for Char $charId.");
            return ['status' => 0, 'error' => 'Invalid battle token'];
        }

        if ($cachedBattle['token'] !== $token || $cachedBattle['mission_id'] !== $missionId) {
            Log::warning("Token/mission mismatch for Char $charId. Expected: {$cachedBattle['token']} / {$cachedBattle['mission_id']}, Got: $token / $missionId");
            return ['status' => 0, 'error' => 'Invalid battle token'];
        }

        if (!$this->validateHash((string)$hash, (string)$missionId . (string)$charId . (string)$token . (string)$score)) {
            Log::warning("Finish hash mismatch for Char $charId Mission $missionId");
            return ['status' => 0, 'error' => 'Invalid battle data'];
        }

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found'];

        $goldReward = isset($cachedBattle['reward_gold']) ? (int)$cachedBattle['reward_gold'] : 20;
        $xpReward = isset($cachedBattle['reward_xp']) ? (int)$cachedBattle['reward_xp'] : 20;

        // Apply Double XP if active
        if ($char->double_xp_expire_at && Carbon::now()->lt(Carbon::parse($char->double_xp_expire_at))) {
            $rate = $char->xp_bonus_rate ?: 0;
            // Apply percentage: e.g. 20% -> x * 1.2
            // If rate is 0 but timer is active, fallback to old behavior or 0?
            // Assuming rate is populated. If 0, no bonus.
            if ($rate > 0) {
                $xpReward = intval($xpReward * (1 + ($rate / 100)));
            }
        }

        $oldGold = $char->gold;
        $oldXp = $char->xp;

        $char->gold += $goldReward;

        // Add XP using the new method which handles caps and leveling
        $levelUp = $char->addXp($xpReward);
        $maxLevels = 85;

        // Pet XP Logic
        if ($char->equipment_pet) {
            $activePet = CharacterPet::where('character_id', $charId)
                ->where('id', $char->equipment_pet)
                ->first();

            if ($activePet) {
                $activePet->xp += $xpReward;

                // Pet Level Up
                while ($activePet->level < $maxLevels) {
                    $petXpReq = XP::where('level', $activePet->level)->value('pet_xp');
                    $petRequired = $petXpReq ?: 999999999;

                    if ($activePet->xp >= $petRequired) {
                        $activePet->level++;
                        Log::info("Pet {$activePet->pet_id} Leveled Up to {$activePet->level}");
                    } else {
                        break;
                    }
                }
                $activePet->save();
            }
        }

        $char->save();

        // Clear Recruits (consumed on mission finish)
        $deleted = DB::table('character_recruits')->where('character_id', $charId)->delete();
        Log::info("Cleared $deleted recruits for Char $charId on mission finish.");

        Cache::forget("battle_token_$charId");

        Log::info("Mission Finished: $missionId. Gold: $oldGold + $goldReward = {$char->gold}. XP: $oldXp + $xpReward = {$char->xp}");

        return [
            'status' => 1,
            'error' => 0,
            'result' => [
                (int)$xpReward,
                (int)$goldReward,
                [] // Item rewards
            ],
            'level' => (int)$char->level,
            'xp' => (int)$char->xp,
            'level_up' => $levelUp,
            'account_tokens' => (int)$char->user->tokens
        ];
    }

    private function validateHash(string $hash, string $payload): bool
    {
        $hash = trim($hash);
        if ($hash === '') {
            return true;
        }

        return match (strlen($hash)) {
            32 => hash('md5', $payload) === $hash,
            40 => hash('sha1', $payload) === $hash,
            64 => hash('sha256', $payload) === $hash,
            default => true,
        };
    }
}