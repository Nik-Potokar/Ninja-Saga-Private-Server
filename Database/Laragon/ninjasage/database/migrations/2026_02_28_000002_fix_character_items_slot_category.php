<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixes character_items rows whose category was set to an origin label
 * ('clan', 'crew', 'shadowwar') instead of the inventory-slot label
 * ('weapon', 'back', 'set', etc.).
 *
 * Root cause: RewardGrantService::resolveItemCategory() queried the Item
 * table first. Item.category stores the origin of the item (which source/event
 * it came from), not the gear slot it occupies. Prefix-based slot detection
 * ran only as a fallback, so clan/crew/shadowwar items were stored with the
 * wrong category and never appeared in the loadout after re-login.
 *
 * Fix: re-derive the category for any row whose item_id prefix maps to a
 * known gear slot but whose stored category does not match.
 */
return new class extends Migration
{
    /** item_id prefix → correct inventory slot category */
    private const PREFIX_MAP = [
        'wpn'       => 'weapon',
        'back'      => 'back',
        'accessory' => 'accessory',
        'set'       => 'set',
        'hair'      => 'hair',
        'material'  => 'material',
        'essential' => 'essential',
    ];

    public function up(): void
    {
        foreach (self::PREFIX_MAP as $prefix => $correctCategory) {
            DB::table('character_items')
                ->where('item_id', 'LIKE', $prefix . '_%')
                ->where('category', '!=', $correctCategory)
                ->update([
                    'category'   => $correctCategory,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Cannot reliably reverse — the original (wrong) category values are
        // not stored anywhere. This migration is intentionally irreversible.
    }
};
