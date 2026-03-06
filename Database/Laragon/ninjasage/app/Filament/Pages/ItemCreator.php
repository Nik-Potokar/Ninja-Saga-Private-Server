<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ItemCreator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?string $navigationLabel = 'Item Creator';
    protected static string|\UnitEnum|null $navigationGroup = 'Game Data';
    protected static ?string $title = 'Item Creator';
    protected string $view = 'filament.pages.item-creator';
    protected static ?int $navigationSort = 12;

    // ── Form State ────────────────────────────────────────────────────────────

    public bool   $isEditing      = false;
    public string $loadItemSelect = '';

    public string $item_category    = 'back';
    public string $item_id          = '';
    public string $item_name        = '';
    public string $item_description = '';

    public int|string $item_level          = 1;
    public int|string $item_price_gold     = 0;
    public int|string $item_price_tokens   = 0;
    public int|string $item_price_pvp      = 0;
    public int|string $item_price_prestige = 0;
    public int|string $item_price_merit    = 0;
    public int|string $item_sell_price     = 0;

    public bool  $item_buyable      = true;
    public bool  $item_buyable_clan = false;
    public bool  $item_premium      = false;
    public bool  $item_sellable     = true;
    public array $item_effects      = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->suggestNextId();
    }

    private function suggestNextId(): void
    {
        $prefix  = $this->item_category;
        $items   = $this->loadLibrary();
        $maxNum  = 0;

        // hair/set IDs: hair_20_0 / set_05_1  — extract the middle number
        $pattern = in_array($prefix, ['hair', 'set'])
            ? '/^' . $prefix . '_(\d+)_[01]$/'
            : '/^' . $prefix . '_(\d+)$/';

        foreach ($items as $item) {
            if (preg_match($pattern, $item['id'] ?? '', $m)) {
                $maxNum = max($maxNum, (int) $m[1]);
            }
        }

        $next = $maxNum + 1;
        $this->item_id = in_array($prefix, ['hair', 'set'])
            ? $prefix . '_' . $next . '_0'
            : $prefix . '_' . $next;
    }

    // ── Reactive: category changed ────────────────────────────────────────────

    public function updatedItemCategory(): void
    {
        $this->item_effects = [];
        $this->isEditing    = false;
        $this->suggestNextId();
    }

    // ── Load existing item into the form ──────────────────────────────────────

    public function loadItem(): void
    {
        $id = trim($this->loadItemSelect);
        if (!$id) return;

        $items = $this->loadLibrary();
        $found = null;
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $id) { $found = $item; break; }
        }

        if (!$found) {
            Notification::make()->title("Item \"{$id}\" not found in library.json.")->danger()->send();
            return;
        }

        $this->item_category    = $this->detectCategory($id);
        $this->item_id          = $found['id'];
        $this->item_name        = $found['name']          ?? '';
        $this->item_description = $found['description']   ?? '';
        $this->item_level       = (int) ($found['level']           ?? 1);
        $this->item_price_gold      = (int) ($found['price_gold']      ?? 0);
        $this->item_price_tokens    = (int) ($found['price_tokens']    ?? 0);
        $this->item_price_pvp       = (int) ($found['price_pvp']       ?? 0);
        $this->item_price_prestige  = (int) ($found['price_prestige']  ?? 0);
        $this->item_price_merit     = (int) ($found['price_merit']     ?? 0);
        $this->item_sell_price      = (int) ($found['sell_price']      ?? 0);
        $this->item_buyable         = (bool) ($found['buyable']        ?? true);
        $this->item_buyable_clan    = (bool) ($found['buyable_clan']   ?? false);
        $this->item_premium         = (bool) ($found['premium']        ?? false);
        $this->item_sellable        = (bool) ($found['sellable']       ?? true);

        if (in_array($this->item_category, ['back', 'accessory'])) {
            $effectsData = $this->loadEffectsFor($this->item_category);
            $entry = null;
            foreach ($effectsData as $e) {
                if (($e['id'] ?? '') === $id) { $entry = $e; break; }
            }
            $this->item_effects = $entry['effects'] ?? [];
        } else {
            $this->item_effects = [];
        }

        $this->isEditing = true;

        Notification::make()
            ->title("Loaded \"{$this->item_name}\" ({$id}) — make changes and click Update Item.")
            ->info()
            ->send();
    }

    // ── Reset to new ──────────────────────────────────────────────────────────

    public function resetToNew(): void
    {
        $this->isEditing        = false;
        $this->loadItemSelect   = '';
        $this->item_name        = '';
        $this->item_description = '';
        $this->item_level       = 1;
        $this->item_price_gold      = 0;
        $this->item_price_tokens    = 0;
        $this->item_price_pvp       = 0;
        $this->item_price_prestige  = 0;
        $this->item_price_merit     = 0;
        $this->item_sell_price      = 0;
        $this->item_buyable         = true;
        $this->item_buyable_clan    = false;
        $this->item_premium         = false;
        $this->item_sellable        = true;
        $this->item_effects         = [];
        $this->suggestNextId();
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Edit Existing Item')
                ->description('Search for any non-weapon item to load it into the form below. After editing click "Update Item". Leave blank to create a new item instead.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Select::make('loadItemSelect')
                            ->label('Load Item to Edit')
                            ->placeholder('Search by name or ID…')
                            ->options(function () {
                                $items = $this->loadLibrary();
                                $opts  = [];
                                foreach ($items as $item) {
                                    $id = $item['id'] ?? '';
                                    if (!$id || str_starts_with($id, 'wpn_')) continue;
                                    $opts[$id] = $id . ' — ' . ($item['name'] ?? '');
                                }
                                return $opts;
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadItem()),
                    ]),
                ])
                ->collapsible()
                ->collapsed(fn () => !$this->isEditing),

            Section::make('Item Category')
                ->description('Choose the type of item. This determines the inventory slot it occupies and whether it supports passive effects.')
                ->schema([
                    Forms\Components\Select::make('item_category')
                        ->label('Category')
                        ->options([
                            'back'      => 'back — Back / cloak item (equips to back slot, supports passive effects)',
                            'hair'      => 'hair — Hairstyle (use _0 for male, _1 for female in the ID)',
                            'set'       => 'set — Costume / clothing set (use _0 for male, _1 for female in the ID)',
                            'accessory' => 'accessory — Ring / earring / accessory (equips to accessory slot, supports passive effects)',
                            'item'      => 'item — Consumable item (used from inventory during or outside battle)',
                            'essential' => 'essential — Special item (e.g. Rename Badge, unique non-consumables)',
                            'material'  => 'material — Crafting material (used in recipes, not equipped)',
                        ])
                        ->required()
                        ->disabled(fn () => $this->isEditing)
                        ->live()
                        ->afterStateUpdated(fn () => $this->updatedItemCategory()),
                ]),

            Section::make('Basic Information')
                ->description('Core item identity. The ID must be unique and permanent — it cannot be changed after creation without directly editing library.json.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('item_id')
                            ->label('Item ID')
                            ->helperText('Auto-suggested as the next available number for the selected category. For hair/set: use _0 (male) or _1 (female) suffix, e.g. hair_120_0. Must not already exist in library.json.')
                            ->required()
                            ->disabled(fn () => $this->isEditing)
                            ->extraInputAttributes(['class' => 'font-mono']),

                        Forms\Components\TextInput::make('item_name')
                            ->label('Name')
                            ->required()
                            ->placeholder('e.g. Phoenix Feather Cloak'),
                    ]),

                    Forms\Components\Textarea::make('item_description')
                        ->label('Description')
                        ->helperText('Shown in the item tooltip popup. For back/accessory items, describe the passive effects so players know what the item does before buying.')
                        ->rows(3)
                        ->placeholder('e.g. A cloak woven from phoenix feathers. Reduces incoming damage by 10%.'),
                ]),

            Section::make('Pricing & Availability')
                ->description('How much the item costs across each currency and whether players can buy or sell it.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('item_level')
                            ->label('Level Required')
                            ->helperText('Minimum character level to equip or use this item. Set to 1 if there is no restriction.')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(200),

                        Forms\Components\TextInput::make('item_sell_price')
                            ->label('Sell Price (Gold)')
                            ->helperText('Gold the player receives when selling this item back to the vendor. Set to 0 if unsellable.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('item_price_gold')
                            ->label('Price (Gold)')
                            ->helperText('Standard shop gold cost. Set to 0 if gold is not a valid purchase method.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('item_price_tokens')
                            ->label('Price (Tokens)')
                            ->helperText('Premium token cost. Set to 0 for non-premium items.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('item_price_pvp')
                            ->label('Price (PvP Points)')
                            ->helperText('PvP shop cost. Set to 0 if not available in the PvP shop.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('item_price_prestige')
                            ->label('Price (Prestige)')
                            ->helperText('Prestige shop cost. Set to 0 if not applicable.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('item_price_merit')
                            ->label('Price (Clan Merit)')
                            ->helperText('Clan merit cost. Set to 0 if not in the clan shop.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Grid::make(4)->schema([
                        Forms\Components\Toggle::make('item_buyable')
                            ->label('Buyable')
                            ->helperText('When ON this item appears in standard shops and can be purchased.')
                            ->default(true),

                        Forms\Components\Toggle::make('item_buyable_clan')
                            ->label('Buyable (Clan Shop)')
                            ->helperText('When ON available in the clan shop for merit points.')
                            ->default(false),

                        Forms\Components\Toggle::make('item_premium')
                            ->label('Premium Only')
                            ->helperText('When ON the item is flagged as premium.')
                            ->default(false),

                        Forms\Components\Toggle::make('item_sellable')
                            ->label('Sellable')
                            ->helperText('When OFF the item is bound to the character and cannot be sold.')
                            ->default(true),
                    ]),
                ]),

            Section::make('Passive Effects')
                ->description('Passive bonuses applied while this item is equipped. Only back items and accessories support effects — this section is hidden for all other categories.')
                ->hidden(fn () => !in_array($this->item_category, ['back', 'accessory']))
                ->schema([
                    Forms\Components\Repeater::make('item_effects')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\Select::make('target')
                                    ->label('Target')
                                    ->helperText('Who the passive effect applies to.')
                                    ->options([
                                        'self'  => 'self — the item wearer',
                                        'enemy' => 'enemy — the opponent',
                                    ])
                                    ->required()
                                    ->default('self'),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->helperText('Buff = beneficial to the wearer. Debuff = harmful to the opponent.')
                                    ->options([
                                        'Buff'   => 'Buff — beneficial to wearer',
                                        'Debuff' => 'Debuff — harmful to opponent',
                                    ])
                                    ->required()
                                    ->default('Buff'),
                            ]),

                            Grid::make(2)->schema([
                                Forms\Components\Select::make('effect')
                                    ->label('Effect')
                                    ->helperText('The passive mechanic this item applies. Use search to find the right one.')
                                    ->options(self::effectOptions())
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('effect_name')
                                    ->label('Display Name')
                                    ->helperText('Short label shown in the item tooltip (e.g. "Reduce Damage", "Drain CP").')
                                    ->required()
                                    ->placeholder('e.g. Reduce Damage'),
                            ]),

                            Grid::make(3)->schema([
                                Forms\Components\Select::make('calc_type')
                                    ->label('Calc Type')
                                    ->helperText('How the Amount is calculated. None = no quantity needed.')
                                    ->options([
                                        ''              => 'None — no amount needed (flag effects)',
                                        'number'        => 'number — fixed flat value',
                                        'percent'       => 'percent — % of max HP or CP',
                                        'added_percent' => 'added_percent — stacks additively with % modifiers',
                                    ])
                                    ->default('percent'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->helperText('Magnitude of the effect. Ignored when Calc Type is None.')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\TextInput::make('chance')
                                    ->label('Chance (%)')
                                    ->helperText('Probability the effect triggers per turn/hit (0–100). Use 100 for always-on stat buffs.')
                                    ->numeric()
                                    ->default(100)
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),
                        ])
                        ->addActionLabel('+ Add Passive Effect')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): ?string =>
                            !empty($state['effect'])
                                ? ($state['type'] ?? 'Effect') . ': ' . $state['effect'] . ' → ' . ($state['target'] ?? '?') . ' (' . ($state['chance'] ?? 100) . '%)'
                                : 'New Effect'
                        )
                        ->defaultItems(0)
                        ->collapsible(),
                ]),
        ]);
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(fn () => $this->isEditing ? 'Update Item' : 'Create Item')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),

            Action::make('resetToNew')
                ->label('New Item')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->visible(fn () => $this->isEditing)
                ->action('resetToNew'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        if ($this->isEditing) {
            $this->update();
        } else {
            $this->create();
        }
    }

    // ── Create ────────────────────────────────────────────────────────────────

    private function create(): void
    {
        $id = trim($this->item_id);

        if (!preg_match('/^(back|hair|set|accessory|item|essential|material)_\d+/', $id)) {
            Notification::make()
                ->title('Invalid ID — must start with a known prefix followed by a number (e.g. back_715, hair_120_0, set_60_1, accessory_160).')
                ->danger()
                ->send();
            return;
        }

        $items = $this->loadLibrary();
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $id) {
                Notification::make()->title("ID \"{$id}\" already exists in library.json. Choose a different ID.")->danger()->send();
                return;
            }
        }

        $items[] = $this->buildLibraryEntry($id);
        $libraryPath = base_path('public/game_data/library.json');

        if (file_put_contents($libraryPath, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            Notification::make()->title('Failed to write library.json — check file permissions.')->danger()->send();
            return;
        }
        $this->writeBin($libraryPath, 'library');

        if (in_array($this->item_category, ['back', 'accessory'])) {
            $this->saveEffects($id);
        }

        Notification::make()
            ->title("\"{$this->item_name}\" ({$id}) created and written to library.json + library.bin!")
            ->success()
            ->send();

        $this->resetToNew();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    private function update(): void
    {
        $id = trim($this->item_id);

        $items = $this->loadLibrary();
        $found = false;
        foreach ($items as &$item) {
            if (($item['id'] ?? '') === $id) {
                $item  = $this->buildLibraryEntry($id);
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $items[] = $this->buildLibraryEntry($id);
        }

        $libraryPath = base_path('public/game_data/library.json');
        if (file_put_contents($libraryPath, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            Notification::make()->title('Failed to write library.json — check file permissions.')->danger()->send();
            return;
        }
        $this->writeBin($libraryPath, 'library');

        if (in_array($this->item_category, ['back', 'accessory'])) {
            $this->saveEffects($id);
        }

        Notification::make()
            ->title("\"{$this->item_name}\" ({$id}) updated — library.json and .bin saved!")
            ->success()
            ->send();
    }

    // ── Build library entry ────────────────────────────────────────────────────

    private function buildLibraryEntry(string $id): array
    {
        return [
            'id'             => $id,
            'name'           => trim($this->item_name),
            'description'    => trim($this->item_description),
            'type'           => $this->item_category,
            'level'          => (int) $this->item_level,
            'price_gold'     => (int) $this->item_price_gold,
            'price_tokens'   => (int) $this->item_price_tokens,
            'price_pvp'      => (int) $this->item_price_pvp,
            'price_prestige' => (int) $this->item_price_prestige,
            'price_merit'    => (int) $this->item_price_merit,
            'sell_price'     => (int) $this->item_sell_price,
            'buyable'        => (bool) $this->item_buyable,
            'buyable_clan'   => (bool) $this->item_buyable_clan,
            'premium'        => (bool) $this->item_premium,
            'sellable'       => (bool) $this->item_sellable,
        ];
    }

    // ── Save effects (back or accessory) ──────────────────────────────────────

    private function saveEffects(string $id): void
    {
        $filename    = $this->item_category === 'back' ? 'back_item-effect' : 'accessory-effect';
        $effectsPath = base_path("public/game_data/{$filename}.json");
        $allEffects  = $this->loadEffectsFor($this->item_category);

        $built = !empty($this->item_effects)
            ? array_values(array_map(fn ($e) => [
                'passive'     => true,
                'type'        => $e['type']        ?? 'Buff',
                'target'      => $e['target']      ?? 'self',
                'effect'      => $e['effect']      ?? '',
                'effect_name' => $e['effect_name'] ?? '',
                'duration'    => 0,
                'calc_type'   => $e['calc_type']   ?? '',
                'amount'      => (int) ($e['amount'] ?? 0),
                'chance'      => (int) ($e['chance'] ?? 100),
            ], $this->item_effects))
            : null;

        $found = false;
        foreach ($allEffects as &$e) {
            if (($e['id'] ?? '') === $id) {
                $e     = $built !== null ? ['id' => $id, 'effects' => $built] : null;
                $found = true;
                break;
            }
        }
        unset($e);
        $allEffects = array_values(array_filter($allEffects));

        if (!$found && $built !== null) {
            $allEffects[] = ['id' => $id, 'effects' => $built];
        }

        if (file_put_contents($effectsPath, json_encode($allEffects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
            $this->writeBin($effectsPath, $filename);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function detectCategory(string $id): string
    {
        return match(true) {
            str_starts_with($id, 'back_')      => 'back',
            str_starts_with($id, 'hair_')      => 'hair',
            str_starts_with($id, 'set_')        => 'set',
            str_starts_with($id, 'accessory_') => 'accessory',
            str_starts_with($id, 'item_')      => 'item',
            str_starts_with($id, 'essential_') => 'essential',
            str_starts_with($id, 'material_')  => 'material',
            default                            => 'item',
        };
    }

    private function loadLibrary(): array
    {
        $path = base_path('public/game_data/library.json');
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function loadEffectsFor(string $category): array
    {
        $filename = $category === 'back' ? 'back_item-effect' : 'accessory-effect';
        $path = base_path("public/game_data/{$filename}.json");
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function writeBin(string $jsonPath, string $binName): bool
    {
        $json = @file_get_contents($jsonPath);
        if ($json === false) return false;
        $compressed = gzcompress($json, 6);
        if ($compressed === false) return false;
        return (bool) file_put_contents(base_path('public/game_data/' . $binName . '.bin'), $compressed);
    }

    // ── Passive Effect Options ────────────────────────────────────────────────

    private static function effectOptions(): array
    {
        return [
            'Stat Boosts' => [
                'damage_increase'    => 'damage_increase — increases outgoing damage by amount',
                'critical_increase'  => 'critical_increase — increases critical hit rate by amount',
                'accuracy_increase'  => 'accuracy_increase — increases hit accuracy by amount',
                'dodge_increase'     => 'dodge_increase — increases dodge / evasion chance by amount',
                'agility_increase'   => 'agility_increase — increases speed and evasion stat',
                'max_hp_increase'    => 'max_hp_increase — raises maximum HP by amount',
                'max_cp_increase'    => 'max_cp_increase — raises maximum CP by amount',
                'damage_reduce'      => 'damage_reduce — reduces all incoming damage by amount (calc_type: percent)',
                'block_damage'       => 'block_damage — blocks a flat amount of damage per hit before HP loss',
                'ignore_dodge'       => 'ignore_dodge — attacks cannot be dodged by the opponent',
                'power_up'           => 'power_up — general outgoing damage multiplier',
                'power_up_by_hp'     => 'power_up_by_hp — damage boost scales with remaining HP',
                'protection'         => 'protection — reduces incoming damage by % (similar to damage_reduce)',
                'combustion_increase'=> 'combustion_increase — increases trigger chance of fire-based effects',
                'dodge_decrease'     => 'dodge_decrease — reduces the opponent\'s dodge/evasion chance',
                'attack_reduce_gen_cooldown' => 'attack_reduce_gen_cooldown — each attack reduces skill cooldowns',
            ],

            'HP Recovery' => [
                'hp_recover'              => 'hp_recover — passive HP regeneration each turn',
                'hp_recover_below'        => 'hp_recover_below — HP regen when HP is below amount % threshold',
                'hp_recover_every_turn'   => 'hp_recover_every_turn — guaranteed HP restore each turn',
                'recover_hp_every_turn'   => 'recover_hp_every_turn — HP restored each combat turn (alternate key)',
                'hp_recover_with_attack'  => 'hp_recover_with_attack — heals for amount HP on each successful hit',
                'hp_recover_below_cp'     => 'hp_recover_below_cp — HP regen when current CP is below threshold',
                'recover_hp_buff'         => 'recover_hp_buff — HP recovery active when a buff is on the wearer',
                'recover_hp_debuff'       => 'recover_hp_debuff — HP recovery active when a debuff is on the wearer',
                'recover_hp_after_critical'   => 'recover_hp_after_critical — restores HP after landing a critical hit',
                'recover_hp_after_purify'     => 'recover_hp_after_purify — restores HP when the wearer is purified',
                'recover_hp_when_attacked'    => 'recover_hp_when_attacked — restores HP each time the wearer is hit',
                'recover_hp_by_cp_cost'       => 'recover_hp_by_cp_cost — restores HP proportional to CP spent on skills',
            ],

            'CP Recovery' => [
                'cp_recover'              => 'cp_recover — passive CP regeneration each turn',
                'cp_recover_below'        => 'cp_recover_below — CP regen when CP is below amount % threshold',
                'cp_recover_with_attack'  => 'cp_recover_with_attack — restores CP on each successful hit',
                'recover_cp_buff'         => 'recover_cp_buff — CP recovery active when a buff is on the wearer',
                'recover_cp_debuff'       => 'recover_cp_debuff — CP recovery active when a debuff is on the wearer',
                'recover_cp_after_purify' => 'recover_cp_after_purify — restores CP when the wearer is purified',
                'hp_cp_recover'           => 'hp_cp_recover — restores both HP and CP each turn',
                'recover_hp_cp_buff'      => 'recover_hp_cp_buff — HP+CP recovery while a buff is active',
                'recover_hp_cp_debuff'    => 'recover_hp_cp_debuff — HP+CP recovery while a debuff is active',
            ],

            'Drain Effects' => [
                'drain_cp_with_attack' => 'drain_cp_with_attack — steals amount % of enemy CP on each hit',
                'drain_hp_with_attack' => 'drain_hp_with_attack — steals amount % of enemy HP on each hit',
                'absorb_damage_to_cp'  => 'absorb_damage_to_cp — portion of damage received is converted to CP',
                'absorb_damage_to_hp'  => 'absorb_damage_to_hp — portion of damage received is converted back to HP',
                'cp_absorption'        => 'cp_absorption — absorbs enemy CP passively each turn',
                'damage_to_hp'         => 'damage_to_hp — converts a portion of outgoing damage into HP',
                'bloodfeed_attack'     => 'bloodfeed_attack — gains HP proportional to total damage dealt on hit',
            ],

            'Status Infliction (On Hit)' => [
                'inflict_bleeding'    => 'inflict_bleeding — chance to apply Bleeding on each hit',
                'inflict_blind'       => 'inflict_blind — chance to apply Blind on each hit',
                'inflict_burn'        => 'inflict_burn — chance to apply Burn on each hit',
                'inflict_burning'     => 'inflict_burning — chance to apply Burning on each hit',
                'inflict_numb'        => 'inflict_numb — chance to apply Numb on each hit',
                'inflict_petrify'     => 'inflict_petrify — chance to apply Petrify on each hit',
                'inflict_poison'      => 'inflict_poison — chance to apply Poison on each hit',
                'inflict_restriction' => 'inflict_restriction — chance to apply Restriction on each hit',
                'inflict_slow'        => 'inflict_slow — chance to apply Slow on each hit',
                'inflict_weaken'      => 'inflict_weaken — chance to apply Weaken on each hit',
                'stun_when_crit'      => 'stun_when_crit — stuns the target when landing a critical hit',
            ],

            'Attacker Retaliation' => [
                'bleeding_attacker'      => 'bleeding_attacker — inflicts Bleeding on whoever attacks the wearer',
                'blind_attacker'         => 'blind_attacker — inflicts Blind on whoever attacks the wearer',
                'burn_attacker'          => 'burn_attacker — inflicts Burn on whoever attacks the wearer',
                'poison_attacker'        => 'poison_attacker — inflicts Poison on whoever attacks the wearer',
                'concentration_attacker' => 'concentration_attacker — debuffs attacker accuracy when they hit',
            ],

            'Direct Passive Status' => [
                'bleeding'     => 'bleeding — opponent has Bleeding passively while equipped',
                'burn'         => 'burn — opponent has Burn passively while equipped',
                'burning'      => 'burning — opponent has Burning passively while equipped',
                'poison'       => 'poison — opponent has Poison passively while equipped',
                'sleep'        => 'sleep — passive sleep chance on opponent each turn',
                'stun'         => 'stun — passive stun chance on opponent each turn',
                'numb'         => 'numb — opponent is numbed passively while equipped',
                'slow'         => 'slow — opponent is slowed passively while equipped',
                'weaken'       => 'weaken — opponent is weakened passively while equipped',
                'frozen'       => 'frozen — opponent is frozen passively while equipped',
                'petrify'      => 'petrify — opponent is petrified passively while equipped',
                'dark_curse'   => 'dark_curse — dark curse applied passively while equipped',
                'demonic_curse'=> 'demonic_curse — demonic curse applied passively while equipped',
                'restriction'  => 'restriction — opponent is restricted passively while equipped',
            ],

            'Utility & Skill Modifiers' => [
                'accuracy_up_below_hp'      => 'accuracy_up_below_hp — bonus accuracy when HP is below amount %',
                'dodge_damage_bonus'        => 'dodge_damage_bonus — bonus damage after successfully dodging',
                'guard_below_hp'            => 'guard_below_hp — activates damage reduction guard when HP drops below amount %',
                'cp_shield_weapon'          => 'cp_shield_weapon — uses CP to absorb damage instead of HP',
                'reduce_cp_consumption'     => 'reduce_cp_consumption — flat reduction in CP cost for all skills',
                'reduce_cp_consumption_prc' => 'reduce_cp_consumption_prc — % reduction in CP cost for all skills',
                'purify_increase'           => 'purify_increase — increases effectiveness of purify effects',
                'senjutsu_strengthen'       => 'senjutsu_strengthen — boosts damage and effects of sage mode skills',
            ],

            'Advanced & Special' => [
                'rewind'             => 'rewind — reverts the opponent to a previous state',
                'mortal'             => 'mortal — marks the opponent as mortal, enabling instant-kill effects',
                'instant_kill'       => 'instant_kill — instantly defeats opponent if HP is below amount % threshold',
                'insta_reduce_max_cp'=> 'insta_reduce_max_cp — permanently reduces opponent\'s maximum CP',
                'insta_reduce_max_hp'=> 'insta_reduce_max_hp — permanently reduces opponent\'s maximum HP',
                'disperse'           => 'disperse — strips all active buffs from the opponent',
                'dismantle'          => 'dismantle — removes the opponent\'s equipped item passive effects',
                'frostbite'          => 'frostbite — cold frostbite damage over time with slow',
                'hemorrhage'         => 'hemorrhage — heavy bleeding damage over time',
                'internal_injury'    => 'internal_injury — internal chakra damage per turn',
            ],
        ];
    }
}
