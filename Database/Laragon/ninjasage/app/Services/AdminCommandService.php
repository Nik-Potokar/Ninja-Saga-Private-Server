<?php

namespace App\Services;

use App\Models\AdminCommand;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterPet;
use App\Models\CharacterSkill;
use App\Models\Item;
use App\Models\Pet;
use App\Models\Skill;

class AdminCommandService
{
    public function execute(AdminCommand $command, Character $char, array $runtimeParams = []): array
    {
        $params = array_merge($command->params ?? [], $runtimeParams);

        return match ($command->command_type) {
            'give_all_skills'        => $this->giveAllSkills($char),
            'give_all_category'      => $this->giveAllCategory($char, $params['category'] ?? 'weapon'),
            'give_all_hairstyles'    => $this->giveAllHairstyles($char),
            'give_all_pets'          => $this->giveAllPets($char),

            'give_all_crewitems'     => $this->giveAllCrewItems($char),
            'give_all_clanitems'     => $this->giveAllClanItems($char),
            'give_all_weapons'       => $this->giveAllWeapons($char),
            'give_all_spendingitems' => $this->giveAllSpendingItems($char),
            'give_all_shadowwaritems'=> $this->giveAllShadowWarItems($char),
            'give_all_setitems'      => $this->giveAllSetItems($char),
            'give_all_packageitems'  => $this->giveAllPackageItems($char),
            'give_all_materialitems' => $this->giveAllMaterialItems($char),
            'give_all_leaderboarditems'=> $this->giveAllLeaderboardItems($char),
            'give_all_itemitems'     => $this->giveAllItemItems($char),
            'give_all_eventitems'    => $this->giveAllEventItems($char),
            'give_all_essentialitems'=> $this->giveAllEssentialItems($char),
            'give_all_dealitems'     => $this->giveAllDealItems($char),
            'give_all_accessoryitems'=> $this->giveAllAccessoryItems($char),
            'give_all_backitems'     => $this->giveAllBackItems($char),

            'give_all_available'     => $this->giveAllAvailable($char),

            'add_gold'          => $this->addGold($char, (int) ($params['amount'] ?? 0)),
            'add_tokens'        => $this->addTokens($char, (int) ($params['amount'] ?? 0)),
            'set_rank'          => $this->setRank($char, (int) ($params['rank'] ?? 1)),
            'set_level'         => $this->setLevel($char, (int) ($params['level'] ?? 1)),
            default             => ['success' => false, 'message' => "Unknown command type: {$command->command_type}"],
        };
    }

    private function giveAllSkills(Character $char): array
    {
        $skills = Skill::pluck('skill_id');
        $count = 0;
        foreach ($skills as $skillId) {
            $created = CharacterSkill::firstOrCreate([
                'character_id' => $char->id,
                'skill_id'     => $skillId,
            ]);
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new skills to {$char->name}."];
    }

    private function giveAllCategory(Character $char, string $category): array
    {
        $items = Item::where('category', $category)->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => $category]
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new {$category} items to {$char->name}."];
    }

    private function giveAllHairstyles(Character $char): array
    {
        $items = Item::where('category', 'hair')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'hair']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new hairstyles to {$char->name}."];
    }

    private function giveAllPets(Character $char): array
    {
        $pets = Pet::all();
        $count = 0;
        foreach ($pets as $pet) {
            $created = CharacterPet::firstOrCreate(
                ['character_id' => $char->id, 'pet_id' => $pet->pet_id],
                ['name' => $pet->name, 'level' => 1, 'xp' => 0]
            );
            if ($created->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new pets to {$char->name}."];
    }

    private function addGold(Character $char, int $amount): array
    {
        $char->gold += $amount;
        $char->save();
        return ['success' => true, 'message' => "Added {$amount} gold to {$char->name}. New total: {$char->gold}."];
    }

    private function addTokens(Character $char, int $amount): array
    {
        if ($char->user) {
            $char->user->tokens += $amount;
            $char->user->save();
        }
        return ['success' => true, 'message' => "Added {$amount} tokens to {$char->name}'s account."];
    }

    private function setRank(Character $char, int $rank): array
    {
        $char->rank = $rank;
        $char->save();
        return ['success' => true, 'message' => "Set {$char->name}'s rank to {$rank}."];
    }

    private function setLevel(Character $char, int $level): array
    {
        $char->level = $level;
        $char->save();
        return ['success' => true, 'message' => "Set {$char->name}'s level to {$level}."];
    }

        private function giveAllWeapons(Character $char): array
    {
        $items = Item::where('category', 'weapon')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'weapon']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new weapons to {$char->name}."];
    }

        private function giveAllSpendingItems(Character $char): array
    {
        $items = Item::where('category', 'spending')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'spending']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new spending items to {$char->name}."];
    }

    private function giveAllShadowWarItems(Character $char): array
    {
        $items = Item::where('category', 'shadowwar')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'shadowwar']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new shadowwar items to {$char->name}."];
    }

        private function giveAllSetItems(Character $char): array
    {
        $items = Item::where('category', 'set')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'set']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new set items to {$char->name}."];
    }

        private function giveAllPackageItems(Character $char): array
    {
        $items = Item::where('category', 'package')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'package']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new package items to {$char->name}."];
    }

            private function giveAllMaterialItems(Character $char): array
    {
        $items = Item::where('category', 'material')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'material']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new material items to {$char->name}."];
    }

            private function giveAllLeaderboardItems(Character $char): array
    {
        $items = Item::where('category', 'leaderboard')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'leaderboard']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new leaderboard items to {$char->name}."];
    }

    private function giveAllItemItems(Character $char): array
    {
        $items = Item::where('category', 'item')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'item']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new item (Category Item) items to {$char->name}."];
    }

    private function giveAllEventItems(Character $char): array
    {
        $items = Item::where('category', 'event')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'event']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new event items to {$char->name}."];
    }

        private function giveAllEssentialItems(Character $char): array
    {
        $items = Item::where('category', 'essential')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'essential']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new essential items to {$char->name}."];
    }

            private function giveAllDealItems(Character $char): array
    {
        $items = Item::where('category', 'deal')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'deal']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new deal items to {$char->name}."];
    }

        private function giveAllCrewItems(Character $char): array
    {
        $items = Item::where('category', 'crew')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => $this->resolveInventoryCategory($itemId)]
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new crew items to {$char->name}."];
    }

    private function giveAllClanItems(Character $char): array
    {
        $items = Item::where('category', 'clan')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => $this->resolveInventoryCategory($itemId)]
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new clan items to {$char->name}."];
    }

    private function resolveInventoryCategory(string $itemId): string
    {
        if (str_starts_with($itemId, 'wpn_'))       return 'weapon';
        if (str_starts_with($itemId, 'back_'))      return 'back';
        if (str_starts_with($itemId, 'set_'))       return 'set';
        if (str_starts_with($itemId, 'hair_'))      return 'hair';
        if (str_starts_with($itemId, 'accessory_')) return 'accessory';
        if (str_starts_with($itemId, 'material_'))  return 'material';
        if (str_starts_with($itemId, 'essential_')) return 'essential';
        return 'item';
    }

                private function giveAllAccessoryItems(Character $char): array
    {
        $items = Item::where('category', 'accessory')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'accessory']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new accessory items to {$char->name}."];
    }

                    private function giveAllBackItems(Character $char): array
    {
        $items = Item::where('category', 'back')->pluck('item_id');
        $count = 0;
        foreach ($items as $itemId) {
            $item = CharacterItem::firstOrCreate(
                ['character_id' => $char->id, 'item_id' => $itemId],
                ['quantity' => 1, 'category' => 'back']
            );
            if ($item->wasRecentlyCreated) {
                $count++;
            }
        }
        return ['success' => true, 'message' => "Granted {$count} new backitems to {$char->name}."];
    }

    public function giveAllAvailable(Character $char): array
{
    // List all "give_all" methods except giveAllCategory
    $giveAllMethods = [
        'giveAllSkills',
        'giveAllHairstyles',
        'giveAllPets',
        'giveAllCrewItems',
        'giveAllClanItems',
        'giveAllWeapons',
        'giveAllSpendingItems',
        'giveAllShadowWarItems',
        'giveAllSetItems',
        'giveAllPackageItems',
        'giveAllMaterialItems',
        'giveAllLeaderboardItems',
        'giveAllItemItems',
        'giveAllEventItems',
        'giveAllEssentialItems',
        'giveAllDealItems',
        'giveAllAccessoryItems',
        'giveAllBackItems',
    ];

    $results = [];

    foreach ($giveAllMethods as $method) {
        if (method_exists($this, $method)) {
            $results[] = $this->$method($char);
        }
    }

    // Combine messages for a summary
    $summary = collect($results)
        ->pluck('message')
        ->implode(' | ');

    return [
        'success' => true,
        'message' => "All available items granted to {$char->name}: {$summary}",
    ];
}
}