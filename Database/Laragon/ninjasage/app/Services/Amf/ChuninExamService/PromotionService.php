<?php

namespace App\Services\Amf\ChuninExamService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\Item;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    use ValidatesSession;

    /**
     * promoteToChunin
     * Parameters: [sessionKey, charId]
     */
    public function promoteToChunin($sessionKey, $charId)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF ChuninExam.promoteToChunin: Char $charId");

        $char = Character::find($charId);
        if (!$char) {
            return ['status' => 0, 'error' => 'Character not found'];
        }

        if ($char->rank < Character::RANK_GENIN) {
            return ['status' => 2, 'result' => 'You must be a Genin to take the Chunin Exam!'];
        }
        if ($char->level < 20) {
            return ['status' => 2, 'result' => 'You must be Level 20 to take the Chunin Exam!'];
        }

        // Verify all stages are completed (status 2)
        $progress = explode(',', $char->chunin_exam_progress);
        $allCompleted = true;
        foreach ($progress as $status) {
            if ($status != "2") {
                $allCompleted = false;
                break;
            }
        }

        if (!$allCompleted) {
            return ['status' => 2, 'result' => 'You must complete all exam stages first!'];
        }

        // Replay path: already Chunin or above — reset progress so they can go again, no rewards.
        if ($char->rank >= Character::RANK_CHUNIN) {
            $char->chunin_exam_progress = '1,1,1,1,1';
            $char->save();
            Log::info("Chunin Exam replay completed for Char $charId (rank {$char->rank}), progress reset.");
            return ['status' => 1, 'error' => 0, 'rewards' => [], 'result' => 'Promoted'];
        }

        // Add Rewards (Chunin Package)
        $genderSuffix = $char->gender == 0 ? '_0' : '_1';
        $setId = 'set_150' . $genderSuffix;
        $weaponId = 'wpn_794';
        $skillId = 'skill_109';
        $tokenAmount = 200;

        $setConfig = Item::where('item_id', $setId)->first();
        if (!$setConfig) {
            $setId = 'set_150'; // Fallback to non-suffixed
            $setConfig = Item::where('item_id', $setId)->first();
        }

        if ($setConfig) {
            $charItem = CharacterItem::where('character_id', $charId)->where('item_id', $setId)->first();
            if ($charItem) {
                $charItem->quantity += 1;
                $charItem->save();
            } else {
                CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $setId,
                    'quantity' => 1,
                    'category' => 'set'
                ]);
            }
            Log::info("Awarded Chunin Set $setId to Char $charId");
        } else {
            Log::warning("Chunin Reward Item set_150 not found in DB.");
        }

        $weaponConfig = Item::where('item_id', $weaponId)->first();
        if ($weaponConfig) {
            $charWeapon = CharacterItem::where('character_id', $charId)->where('item_id', $weaponId)->first();
            if ($charWeapon) {
                $charWeapon->quantity += 1;
                $charWeapon->save();
            } else {
                CharacterItem::create([
                    'character_id' => $charId,
                    'item_id' => $weaponId,
                    'quantity' => 1,
                    'category' => 'weapon'
                ]);
            }
            Log::info("Awarded Chunin Weapon $weaponId to Char $charId");
        } else {
            Log::warning("Chunin Reward Weapon wpn_794 not found in DB.");
        }

        CharacterSkill::firstOrCreate([
            'character_id' => $charId,
            'skill_id' => $skillId
        ]);

        $user = $char->user;
        if ($user) {
            $user->tokens += $tokenAmount;
            $user->save();
        }

        // Promote
        $updated = $char->update([
            'rank' => 3, // Chunin
            'chunin_claimed' => 1
        ]);

        Log::info("Chunin Promotion Char $charId: Updated=$updated");

        // Construct Reward Array for Client (Array of Strings)
        // The client code in main.as:makeChunin iterates over the array and adds items based on prefix.
        $rewards = [
            $setId,
            $weaponId,
            $skillId,
            'tokens_' . $tokenAmount
        ];

        return [
            'status' => 1,
            'error' => 0,
            'rewards' => $rewards,
            'result' => 'Promoted'
        ];
    }
}
