<?php

namespace App\Services\Amf\PetService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\Pet;
use App\Models\PetVillaSlot;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VillaService
{
    use ValidatesSession;

    // Training duration in seconds for a pet to train to (current_level + 1).
    // Mirrors the client: getTrainData(pet_level + 1, "second") = pet_level * 1440
    private function trainingSeconds(int $petLevel): int
    {
        return $petLevel * 1440;
    }

    // Gold cost to start training.
    // Mirrors the client: getTrainData(pet_level + 1, "gold") = pet_level * 2000
    private function trainingGold(int $petLevel): int
    {
        return $petLevel * 2000;
    }

    // Skip price in tokens = ceil(remaining_seconds / 60), min 1.
    private function skipPrice(int $remainingSeconds): int
    {
        return max(1, (int) ceil($remainingSeconds / 60));
    }

    // Format training duration for display in the pet list (hourTxt field).
    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }

    /**
     * Ensure the character has exactly 4 pet villa slot rows.
     * Slots 0–1 default to status=1 (waiting), slots 2–3 default to status=0 (locked).
     */
    private function initSlots(int $charId): void
    {
        $existing = PetVillaSlot::where('character_id', $charId)
            ->pluck('slot_index')
            ->toArray();

        for ($i = 0; $i < 4; $i++) {
            if (!in_array($i, $existing)) {
                PetVillaSlot::create([
                    'character_id'    => $charId,
                    'slot_index'      => $i,
                    'status'          => $i < 2 ? 1 : 0, // 0/1 open, 2/3 locked
                    'pet_instance_id' => null,
                    'training_ends_at'=> null,
                    'gold_spent'      => 0,
                ]);
            }
        }
    }

    /**
     * Auto-complete any training that has finished since the last request.
     */
    private function autoCompleteTraining(int $charId): void
    {
        $now = time();
        PetVillaSlot::where('character_id', $charId)
            ->where('status', 2)
            ->where('training_ends_at', '<=', $now)
            ->update(['status' => 3]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * getVillaData
     * Params: [char_id, session_key]
     */
    public function getVillaData($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId = (int) $params[0];
        Log::info("AMF PetService.getVillaData: Char $charId");

        $this->initSlots($charId);
        $this->autoCompleteTraining($charId);

        $now = time();

        // Build slots array (always 4 items, ordered by slot_index).
        $dbSlots = PetVillaSlot::where('character_id', $charId)
            ->orderBy('slot_index')
            ->get()
            ->keyBy('slot_index');

        $slotsOut = [];
        for ($i = 0; $i < 4; $i++) {
            $slot = $dbSlots->get($i);
            $remaining = 0;
            $skipCost  = 0;

            if ($slot && $slot->status === 2 && $slot->training_ends_at) {
                $remaining = max(0, $slot->training_ends_at - $now);
                $skipCost  = $this->skipPrice($remaining);
            }

            $slotsOut[] = [
                'status'       => $slot ? (int) $slot->status : 0,
                'pet_id'       => $slot && $slot->pet_instance_id ? (int) $slot->pet_instance_id : 0,
                'skip_price'   => $skipCost,
                'completed_at' => $remaining, // remaining seconds; client ticks it down
            ];
        }

        // Build pets array from character_pets.
        $charPets = CharacterPet::where('character_id', $charId)->get();
        $petsOut  = [];

        foreach ($charPets as $cp) {
            $config   = Pet::where('pet_id', $cp->pet_id)->first();
            $duration = $this->trainingSeconds($cp->level);
            $gold     = $this->trainingGold($cp->level);

            $petsOut[] = [
                'pet_id'          => (int) $cp->id,        // instance ID
                'pet_name'        => $cp->name ?? ($config->name ?? 'Unknown Pet'),
                'pet_level'       => (int) $cp->level,
                'pet_xp'          => (int) $cp->xp,
                'pet_swf'         => $cp->pet_id,           // type ID for SWF loading
                'train_time_text' => $this->formatDuration($duration),
                'train_gold'      => $gold,
            ];
        }

        return [
            'status' => 1,
            'slots'  => $slotsOut,
            'pets'   => $petsOut,
        ];
    }

    /**
     * trainPet
     * Params: [char_id, session_key, pet_instance_id]
     */
    public function trainPet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId        = (int) $params[0];
        $petInstanceId = (int) $params[2];
        Log::info("AMF PetService.trainPet: Char $charId Pet $petInstanceId");

        $pet = CharacterPet::where('id', $petInstanceId)
            ->where('character_id', $charId)
            ->first();

        if (!$pet) {
            return ['status' => 0, 'error' => 'Pet not found.'];
        }

        // Check pet is not already in a slot.
        $inSlot = PetVillaSlot::where('character_id', $charId)
            ->where('pet_instance_id', $petInstanceId)
            ->whereIn('status', [2, 3])
            ->first();

        if ($inSlot) {
            return ['status' => 2, 'result' => 'This pet is already in training or waiting for checkout.'];
        }

        // Find an available waiting slot (status=1).
        $this->initSlots($charId);
        $slot = PetVillaSlot::where('character_id', $charId)
            ->where('status', 1)
            ->orderBy('slot_index')
            ->first();

        if (!$slot) {
            return ['status' => 2, 'result' => 'No available training slots. Unlock more slots or wait for current training to finish.'];
        }

        $goldCost = $this->trainingGold($pet->level);
        $char     = Character::find($charId);

        if (!$char || $char->gold < $goldCost) {
            return ['status' => 2, 'result' => "Not enough gold. Training requires {$goldCost} gold."];
        }

        $duration   = $this->trainingSeconds($pet->level);
        $endsAt     = time() + $duration;

        DB::transaction(function () use ($char, $goldCost, $slot, $petInstanceId, $endsAt) {
            $char->decrement('gold', $goldCost);
            $slot->update([
                'status'           => 2,
                'pet_instance_id'  => $petInstanceId,
                'training_ends_at' => $endsAt,
                'gold_spent'       => $goldCost,
            ]);
        });

        return ['status' => 1, 'result' => 'Training started!'];
    }

    /**
     * skipTraining
     * Params: [char_id, session_key, pet_instance_id]
     * Client deducts tokens locally on success.
     */
    public function skipTraining($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId        = (int) $params[0];
        $petInstanceId = (int) $params[2];
        Log::info("AMF PetService.skipTraining: Char $charId Pet $petInstanceId");

        $slot = PetVillaSlot::where('character_id', $charId)
            ->where('pet_instance_id', $petInstanceId)
            ->where('status', 2)
            ->first();

        if (!$slot) {
            return ['status' => 0, 'error' => 'Training slot not found.'];
        }

        $now       = time();
        $remaining = max(0, $slot->training_ends_at - $now);
        $cost      = $this->skipPrice($remaining);

        $char = Character::with('user')->find($charId);
        if (!$char || !$char->user) {
            return ['status' => 0, 'error' => 'Character not found.'];
        }

        if ($char->user->tokens < $cost) {
            return ['status' => 2, 'result' => "Not enough tokens. Skip costs {$cost} tokens."];
        }

        DB::transaction(function () use ($char, $cost, $slot) {
            $char->user->decrement('tokens', $cost);
            $slot->update(['status' => 3, 'training_ends_at' => time()]);
        });

        return ['status' => 1, 'result' => 'Training skipped! Your pet is ready for checkout.'];
    }

    /**
     * cancelTraining
     * Params: [char_id, session_key, pet_instance_id]
     * Gold is NOT refunded (as the client's confirmation dialog states).
     */
    public function cancelTraining($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId        = (int) $params[0];
        $petInstanceId = (int) $params[2];
        Log::info("AMF PetService.cancelTraining: Char $charId Pet $petInstanceId");

        $slot = PetVillaSlot::where('character_id', $charId)
            ->where('pet_instance_id', $petInstanceId)
            ->where('status', 2)
            ->first();

        if (!$slot) {
            return ['status' => 0, 'error' => 'Training slot not found.'];
        }

        $slot->update([
            'status'           => 1,
            'pet_instance_id'  => null,
            'training_ends_at' => null,
            'gold_spent'       => 0,
        ]);

        return ['status' => 1, 'result' => 'Training cancelled.'];
    }

    /**
     * checkoutPet
     * Params: [char_id, session_key, pet_instance_id]
     * Levels up the pet and frees the slot.
     */
    public function checkoutPet($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId        = (int) $params[0];
        $petInstanceId = (int) $params[2];
        Log::info("AMF PetService.checkoutPet: Char $charId Pet $petInstanceId");

        $slot = PetVillaSlot::where('character_id', $charId)
            ->where('pet_instance_id', $petInstanceId)
            ->where('status', 3)
            ->first();

        if (!$slot) {
            return ['status' => 0, 'error' => 'No completed training found for this pet.'];
        }

        $pet = CharacterPet::where('id', $petInstanceId)
            ->where('character_id', $charId)
            ->first();

        if (!$pet) {
            return ['status' => 0, 'error' => 'Pet not found.'];
        }

        DB::transaction(function () use ($slot, $pet) {
            // Level up the pet and reset XP.
            $pet->increment('level');
            $pet->update(['xp' => 0]);

            // Free the slot.
            $slot->update([
                'status'           => 1,
                'pet_instance_id'  => null,
                'training_ends_at' => null,
                'gold_spent'       => 0,
            ]);
        });

        // Client reads pet display data from its local this.pets array via findPet().
        return ['status' => 1];
    }

    /**
     * unlockSlots
     * Params: [char_id, session_key, payment_type]  ("tokens" or "kunai")
     */
    public function unlockSlots($params)
    {
        $guard = $this->guardCharacterSessionFromParams($params);
        if ($guard) {
            return $guard;
        }

        $charId      = (int) $params[0];
        $paymentType = $params[2] ?? '';
        Log::info("AMF PetService.unlockSlots: Char $charId Payment $paymentType");

        $this->initSlots($charId);

        $lockedSlot = PetVillaSlot::where('character_id', $charId)
            ->where('status', 0)
            ->orderBy('slot_index')
            ->first();

        if (!$lockedSlot) {
            return ['status' => 2, 'result' => 'All slots are already unlocked.'];
        }

        $char = Character::with('user')->find($charId);
        if (!$char || !$char->user) {
            return ['status' => 0, 'error' => 'Character not found.'];
        }

        if ($paymentType === 'tokens') {
            $cost = 400;
            if ($char->user->tokens < $cost) {
                return ['status' => 2, 'result' => "Not enough tokens. Unlocking requires {$cost} tokens."];
            }
            DB::transaction(function () use ($char, $cost, $lockedSlot) {
                $char->user->decrement('tokens', $cost);
                $lockedSlot->update(['status' => 1]);
            });
        } elseif ($paymentType === 'kunai') {
            $cost    = 300;
            $kunai   = CharacterItem::where('character_id', $charId)
                ->where('item_id', 'material_1002')
                ->first();

            if (!$kunai || $kunai->quantity < $cost) {
                return ['status' => 2, 'result' => "Not enough Friendship Kunai. Unlocking requires {$cost}."];
            }
            DB::transaction(function () use ($kunai, $cost, $lockedSlot) {
                if ($kunai->quantity - $cost <= 0) {
                    $kunai->delete();
                } else {
                    $kunai->decrement('quantity', $cost);
                }
                $lockedSlot->update(['status' => 1]);
            });
        } else {
            return ['status' => 0, 'error' => 'Invalid payment type.'];
        }

        return ['status' => 1];
    }
}
