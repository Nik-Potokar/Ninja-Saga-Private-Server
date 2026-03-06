<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Item;

class FixItemCategories extends Command
{
    protected $signature = 'item:fix-categories';
    protected $description = 'Fixes item categories based on ID prefixes.';

    public function handle()
    {
        $items = Item::all();
        $count = 0;

        foreach ($items as $item) {
            $oldCategory = $item->category;
            $newCategory = $oldCategory;

            if ($oldCategory === 'item' || $oldCategory === '') {
                if (str_starts_with($item->item_id, 'wpn_')) $newCategory = 'weapon';
                elseif (str_starts_with($item->item_id, 'back_')) $newCategory = 'back';
                elseif (str_starts_with($item->item_id, 'set_')) $newCategory = 'set';
                elseif (str_starts_with($item->item_id, 'hair_')) $newCategory = 'hair';
                elseif (str_starts_with($item->item_id, 'accessory_')) $newCategory = 'accessory';
                elseif (str_starts_with($item->item_id, 'material_')) $newCategory = 'material';
                elseif (str_starts_with($item->item_id, 'essential_')) $newCategory = 'essential';
            }

            if ($newCategory !== $oldCategory) {
                $item->category = $newCategory;
                $item->save();
                $this->info("Updated {$item->name} ({$item->item_id}) category: $oldCategory -> $newCategory");
                $count++;
            }
        }

        $this->info("Fixed categories for $count items.");
    }
}
