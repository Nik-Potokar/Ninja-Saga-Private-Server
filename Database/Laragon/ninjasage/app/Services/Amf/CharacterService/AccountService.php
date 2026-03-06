<?php

namespace App\Services\Amf\CharacterService;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\User;
use App\Services\Amf\Concerns\ValidatesSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountService
{
    use ValidatesSession;

    /**
     * deleteCharacter
     */
    public function deleteCharacter($charId, $sessionKey, $username, $password)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        Log::info("AMF Delete Character: Char $charId for User $username");

        try {
            $user = User::where('username', $username)->first();
            if (!$user || !Hash::check($password, $user->password)) {
                return ['status' => 2, 'result' => 'Invalid password!'];
            }

            $char = Character::where('id', $charId)->where('user_id', $user->id)->first();
            if (!$char) {
                return ['status' => 2, 'result' => 'Character not found!'];
            }

            $char->delete();
            return ['status' => 1];

        } catch (\Exception $e) {
            Log::error("Delete Character Error: " . $e->getMessage());
            return ['status' => 0, 'error' => 'Internal Server Error'];
        }
    }

    /**
     * convertTokensToGold
     * Params: [sessionKey, charId, tokenAmount]
     * Rate: 1 token = 1,000 gold. Max: 1,000,000 tokens per call.
     */
    public function convertTokensToGold($sessionKey, $charId, $tokenAmount)
    {
        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        $amount = (int)$tokenAmount;

        if ($amount < 1) {
            return ['status' => 2, 'result' => 'Please Insert At Least 1 Token!'];
        }

        if ($amount > 1000000) {
            return ['status' => 2, 'result' => 'Maximum conversion is 1,000,000 tokens.'];
        }

        Log::info("AMF CharacterService.convertTokensToGold: Char $charId Amount $amount");

        $char = Character::find((int)$charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found.'];

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found.'];

        if ($user->tokens < $amount) {
            return ['status' => 2, 'result' => 'Not enough tokens!'];
        }

        $user->tokens -= $amount;
        $user->save();

        $char->gold += $amount * 1000;
        $char->save();

        return [
            'status'          => 1,
            'character_gold'  => (int)$char->gold,
            'account_tokens'  => (int)$user->tokens,
        ];
    }

    /**
     * renameCharacter
     */
    public function renameCharacter($charIdOrParams, $sessionKey = null, $newName = null)
    {
        if (is_array($charIdOrParams)) {
            $first = $charIdOrParams[0] ?? null;
            $second = $charIdOrParams[1] ?? null;
            $third = $charIdOrParams[2] ?? null;

            if (is_string($first) && strlen($first) === 32) {
                $sessionKey = $first;
                $charId = $second;
                $newName = $third;
            } elseif (is_string($second) && strlen($second) === 32) {
                $charId = $first;
                $sessionKey = $second;
                $newName = $third;
            } else {
                $charId = $first;
                $sessionKey = $second ?? $sessionKey;
                $newName = $third ?? $newName;
            }
        } else {
            if (is_string($charIdOrParams) && strlen($charIdOrParams) === 32 && is_numeric($sessionKey)) {
                $charId = (int) $sessionKey;
                $sessionKey = $charIdOrParams;
            } else {
                $charId = $charIdOrParams;
            }
        }

        $guard = $this->guardCharacterSession((int)$charId, $sessionKey);
        if ($guard) {
            return $guard;
        }

        $newName = is_string($newName) ? trim($newName) : '';
        if (strlen($newName) < 2) {
            return ['status' => 2, 'error' => 'Character name too short.'];
        }

        Log::info("AMF CharacterService.rename: Char $charId NewName $newName");

        $char = Character::find($charId);
        if (!$char) return ['status' => 0, 'error' => 'Character not found.'];

        if (strcasecmp($char->name, $newName) === 0) {
            return ['status' => 2, 'result' => 'Name is unchanged.'];
        }

        if (Character::where('name', $newName)->where('id', '!=', $charId)->exists()) {
            return ['status' => 2, 'error' => 'Character name already taken.'];
        }

        $user = User::find($char->user_id);
        if (!$user) return ['status' => 0, 'error' => 'User not found.'];

        $requiredQty = ($user->account_type >= 1) ? 1 : 3;

        $renameItem = CharacterItem::where('character_id', $charId)
            ->where('item_id', 'essential_01')
            ->first();

        if (!$renameItem || $renameItem->quantity < $requiredQty) {
            return ['status' => 2, 'result' => "You need $requiredQty Rename Badge(s) to rename your character."];
        }

        $char->update(['name' => $newName]);

        $renameItem->quantity -= $requiredQty;
        if ($renameItem->quantity <= 0) {
            $renameItem->delete();
        } else {
            $renameItem->save();
        }

        return ['status' => 1];
    }
}