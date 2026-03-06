<?php

namespace App\Services\Amf\WelcomeLoginService;

use App\Models\Character;
use App\Services\Amf\Concerns\ValidatesSession;
use App\Services\Amf\RewardGrantService;
use Illuminate\Support\Facades\Log;

class ClaimService
{
    use ValidatesSession;

    /**
     * claim
     * Parameters: [charId, sessionKey, rewardIndex]
     */
    public function claim($charId, $sessionKey, $rewardIndex)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF WelcomeLogin.claim: Char $charId Index $rewardIndex");

        try {
            $char = Character::find($charId);
            if (!$char) {
                return ['status' => 0, 'error' => 'Character not found'];
            }

            $claimed = [];
            if ($char->claimed_welcome_rewards !== null && $char->claimed_welcome_rewards !== '') {
                $claimed = explode(',', $char->claimed_welcome_rewards);
            }

            // Check using string comparison
            if (!in_array((string)$rewardIndex, $claimed)) {
                $claimed[] = $rewardIndex;

                $char->claimed_welcome_rewards = implode(',', $claimed);

                // Grant Reward Logic
                $rewardsList = RewardsCatalog::all();
                $rewardStr = $rewardsList[$rewardIndex] ?? '';

                $oldGold = $char->gold;
                $this->grantReward($char, $rewardStr);

                Log::info("WelcomeLogin: Granting $rewardStr. Gold: $oldGold -> $char->gold");

                $char->save();
            } else {
                Log::warning("WelcomeLogin: Reward $rewardIndex already claimed by $charId");
            }

            $rewardsList = RewardsCatalog::all();

            return [
                'status' => 1,
                'error' => 0,
                'rewards' => $rewardsList[$rewardIndex] ?? ''
            ];

        } catch (\Exception $e) {
            Log::error("Welcome Claim Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    private function grantReward($char, $rewardStr)
    {
        if (empty($rewardStr)) return;

        (new RewardGrantService())->grant($char, $rewardStr);
    }
}
