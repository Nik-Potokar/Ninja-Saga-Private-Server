<?php

namespace App\Services\Amf;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\CharacterSkill;
use App\Models\Item;
use App\Models\XP;

class RewardGrantService
{
    public function grant(Character $char, string $rewardStr): bool
    {
        $rewardStr = trim($rewardStr);
        if ($rewardStr === '') {
            return false;
        }

        [$rewardId, $qtyStr] = array_pad(explode(':', $rewardStr, 2), 2, null);
        $quantity = (int)($qtyStr ?? 0);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $rewardId = $this->resolveGenderItemId($rewardId, $char);

        if (str_starts_with($rewardId, 'gold_')) {
            $amount = $this->parseAmount($rewardId, 'gold_');
            $char->gold += $amount * $quantity;
            $char->save();
            return false;
        }

        if (str_starts_with($rewardId, 'tokens_')) {
            $amount = $this->parseAmount($rewardId, 'tokens_');
            if ($char->user) {
                $char->user->tokens += $amount * $quantity;
                $char->user->save();
            }
            return false;
        }

        if (str_starts_with($rewardId, 'tp_')) {
            $amount = $this->parseAmount($rewardId, 'tp_');
            $char->tp += $amount * $quantity;
            $char->save();
            return false;
        }

        if (str_starts_with($rewardId, 'ss_')) {
            $amount = $this->parseAmount($rewardId, 'ss_');
            $char->ss += $amount * $quantity;
            $char->save();
            return false;
        }

        if (str_starts_with($rewardId, 'xp_percent_')) {
            $percent = $this->parseAmount($rewardId, 'xp_percent_');
            $levelUp = false;
            if (!$char->isLevelCapped() && $percent > 0) {
                $xpReq = XP::where('level', $char->level)->value('character_xp') ?: 1000;
                $amount = (int)($xpReq * ($percent / 100));
                $levelUp = $char->addXp($amount * $quantity);
            }
            return $levelUp;
        }

        if (str_starts_with($rewardId, 'xp_')) {
            if (str_contains($rewardId, '%')) {
                $percent = $this->parseAmount($rewardId, 'xp_');
                $levelUp = false;
                if (!$char->isLevelCapped() && $percent > 0) {
                    $xpReq = XP::where('level', $char->level)->value('character_xp') ?: 1000;
                    $amount = (int)($xpReq * ($percent / 100));
                    $levelUp = $char->addXp($amount * $quantity);
                }
                return $levelUp;
            }

            $amount = $this->parseAmount($rewardId, 'xp_');
            if ($char->isLevelCapped()) {
                return false;
            }
            return $char->addXp($amount * $quantity);
        }

        if (str_starts_with($rewardId, 'pet_')) {
            if (!CharacterPet::where('character_id', $char->id)->where('pet_id', $rewardId)->exists()) {
                CharacterPet::create([
                    'character_id' => $char->id,
                    'pet_id' => $rewardId,
                    'name' => 'Pet',
                    'level' => 1,
                    'xp' => 0,
                ]);
            }
            return false;
        }

        if (str_starts_with($rewardId, 'skill_')) {
            if (!CharacterSkill::where('character_id', $char->id)->where('skill_id', $rewardId)->exists()) {
                CharacterSkill::create(['character_id' => $char->id, 'skill_id' => $rewardId]);
            }
            return false;
        }

        $itemId = $rewardId;
        $category = $this->resolveItemCategory($itemId);
        $item = CharacterItem::where('character_id', $char->id)->where('item_id', $itemId)->first();
        if ($item) {
            $item->quantity += $quantity;
            $item->save();
        } else {
            CharacterItem::create([
                'character_id' => $char->id,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'category' => $category,
            ]);
        }

        return false;
    }

    private function parseAmount(string $rewardId, string $prefix): int
    {
        $raw = substr($rewardId, strlen($prefix));
        $digits = preg_replace('/\D+/', '', $raw);
        return $digits === '' ? 0 : (int)$digits;
    }

    private function resolveGenderItemId(string $itemId, Character $char): string
    {
        if (str_contains($itemId, '%s')) {
            $suffix = $char->gender == 0 ? '0' : '1';
            return str_replace('%s', $suffix, $itemId);
        }

        return $itemId;
    }

    private function resolveItemCategory(string $itemId): string
    {
        // Gear slot is determined by item ID prefix. This must take priority over
        // the Item table's category, which reflects origin (clan/crew/shadowwar)
        // not the inventory slot the item belongs to.
        $prefix = explode('_', $itemId)[0] ?? '';
        $slotCategory = match ($prefix) {
            'wpn'       => 'weapon',
            'back'      => 'back',
            'accessory' => 'accessory',
            'set'       => 'set',
            'hair'      => 'hair',
            'material'  => 'material',
            'essential' => 'essential',
            default     => null,
        };

        if ($slotCategory !== null) {
            return $slotCategory;
        }

        // Fall back to the Item table for non-equipment items (consumables, etc.)
        $itemConfig = Item::where('item_id', $itemId)->first();
        if ($itemConfig && $itemConfig->category) {
            return $itemConfig->category;
        }

        return 'item';
    }
}