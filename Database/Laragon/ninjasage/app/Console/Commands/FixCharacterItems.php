<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Services\Amf\RewardGrantService;
use Illuminate\Console\Command;

class FixCharacterItems extends Command
{
    protected $signature = 'character:fix-items {--dry-run}';
    protected $description = 'Fixes miscategorized character_items and applies currency rewards.';

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $grantService = new RewardGrantService();

        $stats = [
            'total' => 0,
            'currency' => 0,
            'skills' => 0,
            'pets' => 0,
            'reclassified' => 0,
            'deleted' => 0,
            'skipped' => 0,
        ];

        CharacterItem::where('category', 'item')
            ->where('item_id', 'not like', 'item_%')
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($dryRun, $grantService, &$stats) {
                foreach ($items as $item) {
                    $stats['total']++;
                    $itemId = (string)$item->item_id;
                    $qty = (int)$item->quantity;
                    if ($qty <= 0) {
                        $qty = 1;
                    }

                    $char = Character::find($item->character_id);
                    if (!$char) {
                        $stats['skipped']++;
                        continue;
                    }

                    if ($this->isCurrencyReward($itemId)) {
                        if (!$dryRun) {
                            $grantService->grant($char, $itemId . ':' . $qty);
                            $item->delete();
                        }
                        $stats['currency']++;
                        $stats['deleted']++;
                        continue;
                    }

                    if (str_starts_with($itemId, 'skill_')) {
                        if (!$dryRun) {
                            $grantService->grant($char, $itemId . ':' . $qty);
                            $item->delete();
                        }
                        $stats['skills']++;
                        $stats['deleted']++;
                        continue;
                    }

                    if (str_starts_with($itemId, 'pet_')) {
                        if (!$dryRun) {
                            $grantService->grant($char, $itemId . ':' . $qty);
                            $item->delete();
                        }
                        $stats['pets']++;
                        $stats['deleted']++;
                        continue;
                    }

                    $category = $this->resolveItemCategory($itemId);
                    if ($category === 'item') {
                        $stats['skipped']++;
                        continue;
                    }

                    if (!$dryRun) {
                        $item->category = $category;
                        $item->save();
                    }
                    $stats['reclassified']++;
                }
            });

        $this->info('Done.');
        $this->table(
            ['total', 'currency', 'skills', 'pets', 'reclassified', 'deleted', 'skipped'],
            [[
                $stats['total'],
                $stats['currency'],
                $stats['skills'],
                $stats['pets'],
                $stats['reclassified'],
                $stats['deleted'],
                $stats['skipped'],
            ]]
        );

        if ($dryRun) {
            $this->warn('Dry run: no changes were written.');
        }

        return 0;
    }

    private function isCurrencyReward(string $itemId): bool
    {
        return str_starts_with($itemId, 'gold_')
            || str_starts_with($itemId, 'tokens_')
            || str_starts_with($itemId, 'tp_')
            || str_starts_with($itemId, 'xp_');
    }

    private function resolveItemCategory(string $itemId): string
    {
        $itemConfig = Item::where('item_id', $itemId)->first();
        if ($itemConfig && $itemConfig->category) {
            return $itemConfig->category;
        }

        $prefix = explode('_', $itemId)[0] ?? 'item';
        return match ($prefix) {
            'wpn' => 'weapon',
            'back' => 'back',
            'accessory' => 'accessory',
            'set' => 'set',
            'hair' => 'hair',
            'material' => 'material',
            'essential' => 'essential',
            default => 'item',
        };
    }
}
