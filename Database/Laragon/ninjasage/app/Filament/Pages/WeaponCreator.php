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

class WeaponCreator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;
    protected static ?string $navigationLabel = 'Weapon Creator';
    protected static string|\UnitEnum|null $navigationGroup = 'Game Data';
    protected static ?string $title = 'Weapon Creator';
    protected string $view = 'filament.pages.weapon-creator';
    protected static ?int $navigationSort = 11;

    // ── Form State ────────────────────────────────────────────────────────────

    public bool   $isEditing        = false;
    public string $loadWeaponSelect = '';

    public string $wpn_id = '';
    public string $wpn_name = '';
    public string $wpn_description = '';
    public string $wpn_item_type = 'wpn';
    public string $wpn_attack_type = 'attack_01';
    public int|string $wpn_level = 1;
    public int|string $wpn_damage = 0;
    public int|string $wpn_price_gold = 0;
    public int|string $wpn_price_tokens = 0;
    public int|string $wpn_price_pvp = 0;
    public int|string $wpn_price_prestige = 0;
    public int|string $wpn_price_merit = 0;
    public int|string $wpn_sell_price = 0;
    public bool $wpn_buyable = true;
    public bool $wpn_buyable_clan = false;
    public bool $wpn_premium = false;
    public bool $wpn_sellable = true;
    public array $wpn_effects = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->suggestNextId();
    }

    private function suggestNextId(): void
    {
        $items  = $this->loadLibrary();
        $maxNum = 0;
        foreach ($items as $item) {
            if (preg_match('/^wpn_(\d+)$/', $item['id'] ?? '', $m)) {
                $maxNum = max($maxNum, (int) $m[1]);
            }
        }
        $this->wpn_id = 'wpn_' . ($maxNum + 1);
    }

    // ── Load existing weapon into the form ────────────────────────────────────

    public function loadWeapon(): void
    {
        $id = trim($this->loadWeaponSelect);
        if (!$id) return;

        $items  = $this->loadLibrary();
        $weapon = null;
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $id) { $weapon = $item; break; }
        }

        if (!$weapon) {
            Notification::make()->title("Weapon \"{$id}\" not found in library.json.")->danger()->send();
            return;
        }

        $this->wpn_id           = $weapon['id'];
        $this->wpn_name         = $weapon['name']         ?? '';
        $this->wpn_description  = $weapon['description']  ?? '';
        $this->wpn_item_type    = $weapon['type']         ?? 'wpn';
        $this->wpn_attack_type  = $weapon['attack_type']  ?? 'attack_01';
        $this->wpn_level        = (int) ($weapon['level']          ?? 1);
        $this->wpn_damage       = (int) ($weapon['damage']         ?? 0);
        $this->wpn_price_gold   = (int) ($weapon['price_gold']     ?? 0);
        $this->wpn_price_tokens = (int) ($weapon['price_tokens']   ?? 0);
        $this->wpn_price_pvp    = (int) ($weapon['price_pvp']      ?? 0);
        $this->wpn_price_prestige = (int) ($weapon['price_prestige'] ?? 0);
        $this->wpn_price_merit  = (int) ($weapon['price_merit']    ?? 0);
        $this->wpn_sell_price   = (int) ($weapon['sell_price']     ?? 0);
        $this->wpn_buyable      = (bool) ($weapon['buyable']       ?? true);
        $this->wpn_buyable_clan = (bool) ($weapon['buyable_clan']  ?? false);
        $this->wpn_premium      = (bool) ($weapon['premium']       ?? false);
        $this->wpn_sellable     = (bool) ($weapon['sellable']      ?? true);

        // Load effects from weapon-effect.json
        $wpnEffects  = $this->loadWeaponEffects();
        $effectEntry = null;
        foreach ($wpnEffects as $e) {
            if (($e['id'] ?? '') === $id) { $effectEntry = $e; break; }
        }
        $this->wpn_effects = $effectEntry['effects'] ?? [];

        $this->isEditing = true;

        Notification::make()
            ->title("Loaded \"{$this->wpn_name}\" ({$id}) — make your changes and click Update Weapon.")
            ->info()
            ->send();
    }

    // ── Reset back to "create new" mode ───────────────────────────────────────

    public function resetToNew(): void
    {
        $this->isEditing        = false;
        $this->loadWeaponSelect = '';
        $this->wpn_name         = '';
        $this->wpn_description  = '';
        $this->wpn_damage       = 0;
        $this->wpn_effects      = [];
        $this->wpn_price_gold   = 0;
        $this->wpn_price_tokens = 0;
        $this->wpn_price_pvp    = 0;
        $this->wpn_price_prestige = 0;
        $this->wpn_price_merit  = 0;
        $this->wpn_sell_price   = 0;
        $this->wpn_level        = 1;
        $this->wpn_item_type    = 'wpn';
        $this->wpn_attack_type  = 'attack_01';
        $this->wpn_buyable      = true;
        $this->wpn_buyable_clan = false;
        $this->wpn_premium      = false;
        $this->wpn_sellable     = true;
        $this->suggestNextId();
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Edit Existing Weapon / Item')
                ->description('Select a weapon or item from the list to load all its current values into the form below. After editing click "Update Weapon". To create a brand-new weapon instead, leave this blank.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Select::make('loadWeaponSelect')
                            ->label('Load Weapon to Edit')
                            ->placeholder('Search by name or ID…')
                            ->options(function () {
                                $items = $this->loadLibrary();
                                $opts  = [];
                                foreach ($items as $item) {
                                    if (!isset($item['id'])) continue;
                                    $opts[$item['id']] = $item['id'] . ' — ' . ($item['name'] ?? '');
                                }
                                return $opts;
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadWeapon()),
                    ]),
                ])
                ->collapsible()
                ->collapsed(fn () => !$this->isEditing),

            Section::make('Basic Information')
                ->description('Core weapon identity. The ID is how the server and client reference this item everywhere — it must be unique and cannot change after creation without directly editing library.json.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('wpn_id')
                            ->label('Weapon / Item ID')
                            ->helperText('Auto-suggested as the next available wpn number. Format must be wpn_XXX (e.g. wpn_956). For consumable items use item_XXX format (e.g. item_45). Must not already exist in library.json. This ID is permanent once created.')
                            ->required()
                            ->disabled(fn () => $this->isEditing)
                            ->extraInputAttributes(['class' => 'font-mono']),

                        Forms\Components\TextInput::make('wpn_name')
                            ->label('Name')
                            ->helperText('Display name shown in the inventory, shop, and item tooltip header.')
                            ->required()
                            ->placeholder('e.g. Dragon Bone Katana'),
                    ]),

                    Forms\Components\Textarea::make('wpn_description')
                        ->label('Description')
                        ->helperText('Full text shown in the item tooltip popup. Include all passive effects, stat bonuses, and any conditions. Players read this to decide whether to buy the item — be specific about what the effects do.')
                        ->rows(3)
                        ->placeholder('e.g. A sword forged from dragon bone. Increases damage by 15% and drains 10% of enemy CP on each successful weapon attack.'),

                    Grid::make(2)->schema([
                        Forms\Components\Select::make('wpn_item_type')
                            ->label('Item Type')
                            ->helperText('High-level category. "wpn" = weapon used in battle with an attack animation. "item" = consumable used from inventory with no weapon attack. "smoke" = escape/smoke bomb item for fleeing battles.')
                            ->options([
                                'wpn'   => 'wpn — Weapon (has weapon attack animation)',
                                'item'  => 'item — Consumable item (used from inventory)',
                                'smoke' => 'smoke — Smoke bomb / escape item',
                            ])
                            ->required()
                            ->default('wpn'),

                        Forms\Components\Select::make('wpn_attack_type')
                            ->label('Attack Type / Animation Style')
                            ->helperText('Determines which weapon attack animation the client plays in battle and how the character moves. Only applies to "wpn" type items — consumables and smoke bombs use "item" or "smoke" respectively. All attack styles work for any weapon concept — choose the one whose animation fits best.')
                            ->options([
                                'attack_01' => 'attack_01 — Kunai / throwing weapon (default, most common)',
                                'attack_04' => 'attack_04 — Double sickles (dual wielded)',
                                'attack_05' => 'attack_05 — Sai (pronged weapon)',
                                'attack_06' => 'attack_06 — Single sickle',
                                'attack_07' => 'attack_07 — Sword / katana',
                                'attack_08' => 'attack_08 — Double blade sickles (large dual blades)',
                                'attack_09' => 'attack_09 — Boxing gauntlets / fist weapons',
                                'attack_10' => 'attack_10 — Bow or ranged weapon (fires from distance)',
                                'attack_11' => 'attack_11 — Scythe (large sweeping weapon)',
                                'attack_12' => 'attack_12 — Special / unique (custom animation)',
                                'item'      => 'item — Consumable style (no weapon attack animation)',
                                'smoke'     => 'smoke — Smoke bomb animation',
                            ])
                            ->required()
                            ->default('attack_01'),
                    ]),
                ]),

            Section::make('Combat Stats')
                ->description('The core damage stat. Only relevant for weapon-type items (wpn). Consumables should have damage = 0.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('wpn_level')
                            ->label('Level Required')
                            ->helperText('Minimum character level to equip this weapon (1–200). Players below this level see the weapon as locked in their inventory and cannot equip it. Higher level = can have higher damage without being game-breaking at low levels.')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(200),

                        Forms\Components\TextInput::make('wpn_damage')
                            ->label('Weapon Damage')
                            ->helperText('Base weapon damage added to the character\'s physical attack stat on each weapon attack. This is separate from skill damage. Typical range: 100–800 depending on level requirement. Set to 0 for consumable items.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),
                ]),

            Section::make('Pricing & Availability')
                ->description('How much the item costs across each currency type and whether players can buy or sell it.')
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('wpn_price_gold')
                            ->label('Price (Gold)')
                            ->helperText('In-game gold cost from the standard shop. Only active when buyable = ON. Set to 0 if gold is not a valid purchase method for this item.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('wpn_price_tokens')
                            ->label('Price (Tokens)')
                            ->helperText('Premium token cost. Set to 0 for non-premium items. Tokens are the account-level premium currency purchased with real money.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('wpn_price_pvp')
                            ->label('Price (PvP Points)')
                            ->helperText('Live PvP point cost for the PvP shop. Only visible in the PvP shop section. Set to 0 if this item is not available in the PvP shop.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('wpn_price_prestige')
                            ->label('Price (Prestige)')
                            ->helperText('Character prestige cost. Only used in the prestige shop. Set to 0 if not applicable.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('wpn_price_merit')
                            ->label('Price (Clan Merit)')
                            ->helperText('Clan merit point cost. Only used in the clan shop. Set to 0 if not available in the clan shop.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('wpn_sell_price')
                            ->label('Sell Price (Gold)')
                            ->helperText('Gold the player receives when selling this item to the vendor. Should generally be lower than price_gold. Set to 0 if the item cannot be sold (combine with sellable = OFF).')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Grid::make(4)->schema([
                        Forms\Components\Toggle::make('wpn_buyable')
                            ->label('Buyable')
                            ->helperText('When ON this item appears in standard shops and players can purchase it. When OFF it can only be granted by admin commands, drops, or special events.')
                            ->default(true),

                        Forms\Components\Toggle::make('wpn_buyable_clan')
                            ->label('Buyable (Clan Shop)')
                            ->helperText('When ON this item is available for purchase using clan merit points in the clan shop. Requires price_merit to be set.')
                            ->default(false),

                        Forms\Components\Toggle::make('wpn_premium')
                            ->label('Premium Only')
                            ->helperText('When ON the item is classified as premium. Typically paired with a non-zero price_tokens value. Pure cosmetic flag — the actual cost enforcement uses the token price field.')
                            ->default(false),

                        Forms\Components\Toggle::make('wpn_sellable')
                            ->label('Sellable')
                            ->helperText('When ON players can sell this item to the vendor for sell_price gold. When OFF the item is permanently bound to the character and cannot be sold or traded.')
                            ->default(true),
                    ]),
                ]),

            Section::make('Passive Weapon Effects')
                ->description('Passive bonuses and effects applied while this weapon is equipped. All effects here are always active — they do not need to be activated. Add multiple rows to give the weapon multiple passive traits. Leave empty for a plain weapon with only base damage.')
                ->schema([
                    Forms\Components\Repeater::make('wpn_effects')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\Select::make('target')
                                    ->label('Target')
                                    ->helperText('Who the passive effect applies to. Self = the wielder benefits. Enemy = the opponent is debuffed passively while you have this weapon equipped.')
                                    ->options([
                                        'self'  => 'self — the weapon wielder (most buffs go here)',
                                        'enemy' => 'enemy — the opponent (passive debuffs, drains)',
                                    ])
                                    ->required()
                                    ->default('self'),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->helperText('Classification of this passive effect. Buff = beneficial to the wielder. Debuff = harmful to the opponent. Used by the client UI to color-code status icons.')
                                    ->options([
                                        'Buff'   => 'Buff — beneficial to the wielder',
                                        'Debuff' => 'Debuff — harmful to the opponent',
                                    ])
                                    ->required()
                                    ->default('Buff'),
                            ]),

                            Grid::make(2)->schema([
                                Forms\Components\Select::make('effect')
                                    ->label('Effect')
                                    ->helperText('The passive mechanic this weapon applies. Each value maps to a specific server handler. Use the search box to find effects. Only the exact strings listed here are valid — unknown values are silently ignored.')
                                    ->options(self::weaponEffectOptions())
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('effect_name')
                                    ->label('Display Name')
                                    ->helperText('Short label shown in the weapon\'s tooltip and in battle status icons (e.g. "Drain CP", "Increase Damage", "Inflict Poison"). Keep it short — 1 to 3 words.')
                                    ->required()
                                    ->placeholder('e.g. Drain CP'),
                            ]),

                            Grid::make(3)->schema([
                                Forms\Components\Select::make('calc_type')
                                    ->label('Calc Type')
                                    ->helperText('How the Amount field is used. None = no quantity (for flag-type effects). number = fixed flat value (e.g. 30 accuracy). percent = % of a stat (e.g. 20 = 20% of max HP). added_percent = stacks additively with other % modifiers.')
                                    ->options([
                                        ''              => 'None — no amount needed',
                                        'number'        => 'number — fixed flat value (e.g. 30 accuracy)',
                                        'percent'       => 'percent — % of max HP or CP (e.g. 20 = 20%)',
                                        'added_percent' => 'added_percent — stacks additively with % modifiers',
                                    ])
                                    ->default('percent'),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->helperText('Magnitude of the passive effect. Ignored when Calc Type is None. For "number": e.g. 30 = 30 flat accuracy. For "percent": e.g. 20 = 20% of max HP. For "added_percent": e.g. 15 adds 15% to existing multiplier.')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\TextInput::make('chance')
                                    ->label('Chance (%)')
                                    ->helperText('Probability this passive triggers per weapon attack or per turn, 0–100. 100 = always triggers. Use less than 100 for proc-based effects (e.g. 30 = 30% chance to inflict poison on each hit). Stat-buff effects (damage_increase, dodge_increase) should always be 100.')
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
                ->label(fn () => $this->isEditing ? 'Update Weapon' : 'Create Weapon / Item')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),

            Action::make('resetToNew')
                ->label('New Weapon')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->visible(fn () => $this->isEditing)
                ->action('resetToNew'),
        ];
    }

    // ── Save (create or update) ────────────────────────────────────────────────

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
        $wpnId = trim($this->wpn_id);

        if (!preg_match('/^(wpn|item|smoke|material|accessory|back|hair|set|essential)_\d+/', $wpnId)) {
            Notification::make()->title('Invalid ID — must start with a known prefix followed by a number (e.g. wpn_956, item_45).')->danger()->send();
            return;
        }

        $items = $this->loadLibrary();
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $wpnId) {
                Notification::make()->title("ID \"{$wpnId}\" already exists in library.json. Choose a different ID.")->danger()->send();
                return;
            }
        }

        $items[] = $this->buildLibraryEntry($wpnId);
        $libraryPath = base_path('public/game_data/library.json');

        if (file_put_contents($libraryPath, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            Notification::make()->title('Failed to write library.json — check file permissions.')->danger()->send();
            return;
        }
        $this->writeBin($libraryPath, 'library');

        $this->saveEffects($wpnId);

        Notification::make()
            ->title("\"{$this->wpn_name}\" ({$wpnId}) created and written to library.json + library.bin!")
            ->success()
            ->send();

        if (preg_match('/^wpn_(\d+)$/', $wpnId, $m)) {
            $this->wpn_id = 'wpn_' . ((int) $m[1] + 1);
        }
        $this->resetToNew();
    }

    // ── Update ────────────────────────────────────────────────────────────────

    private function update(): void
    {
        $wpnId = trim($this->wpn_id);

        $items = $this->loadLibrary();
        $found = false;
        foreach ($items as &$item) {
            if (($item['id'] ?? '') === $wpnId) {
                $item  = $this->buildLibraryEntry($wpnId);
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $items[] = $this->buildLibraryEntry($wpnId);
        }

        $libraryPath = base_path('public/game_data/library.json');
        if (file_put_contents($libraryPath, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            Notification::make()->title('Failed to write library.json — check file permissions.')->danger()->send();
            return;
        }
        $this->writeBin($libraryPath, 'library');

        $this->saveEffects($wpnId);

        Notification::make()
            ->title("\"{$this->wpn_name}\" ({$wpnId}) updated — library.json and library.bin saved!")
            ->success()
            ->send();
    }

    // ── Shared Helpers ────────────────────────────────────────────────────────

    private function buildLibraryEntry(string $wpnId): array
    {
        return [
            'id'             => $wpnId,
            'name'           => trim($this->wpn_name),
            'description'    => trim($this->wpn_description),
            'type'           => $this->wpn_item_type,
            'attack_type'    => $this->wpn_attack_type,
            'level'          => (int) $this->wpn_level,
            'damage'         => (int) $this->wpn_damage,
            'price_gold'     => (int) $this->wpn_price_gold,
            'price_tokens'   => (int) $this->wpn_price_tokens,
            'price_pvp'      => (int) $this->wpn_price_pvp,
            'price_prestige' => (int) $this->wpn_price_prestige,
            'price_merit'    => (int) $this->wpn_price_merit,
            'sell_price'     => (int) $this->wpn_sell_price,
            'buyable'        => (bool) $this->wpn_buyable,
            'buyable_clan'   => (bool) $this->wpn_buyable_clan,
            'premium'        => (bool) $this->wpn_premium,
            'sellable'       => (bool) $this->wpn_sellable,
        ];
    }

    private function saveEffects(string $wpnId): void
    {
        $effectsPath = base_path('public/game_data/weapon-effect.json');
        $wpnEffects  = $this->loadWeaponEffects();

        $built = !empty($this->wpn_effects)
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
            ], $this->wpn_effects))
            : null;

        $found = false;
        foreach ($wpnEffects as &$e) {
            if (($e['id'] ?? '') === $wpnId) {
                if ($built !== null) {
                    $e = ['id' => $wpnId, 'effects' => $built];
                } else {
                    $e = null; // remove entry if effects were cleared
                }
                $found = true;
                break;
            }
        }
        unset($e);
        $wpnEffects = array_values(array_filter($wpnEffects));

        if (!$found && $built !== null) {
            $wpnEffects[] = ['id' => $wpnId, 'effects' => $built];
        }

        if (file_put_contents($effectsPath, json_encode($wpnEffects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
            $this->writeBin($effectsPath, 'weapon-effect');
        }
    }

    // ── File Helpers ──────────────────────────────────────────────────────────

    private function loadLibrary(): array
    {
        $path = base_path('public/game_data/library.json');
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function loadWeaponEffects(): array
    {
        $path = base_path('public/game_data/weapon-effect.json');
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

    // ── Weapon Effect Option Lists ────────────────────────────────────────────

    private static function weaponEffectOptions(): array
    {
        return [
            'Stat Boosts' => [
                'damage_increase'    => 'damage_increase — increases weapon attack damage by amount (calc_type: percent or number)',
                'critical_increase'  => 'critical_increase — increases critical hit rate by amount',
                'accuracy_increase'  => 'accuracy_increase — increases hit accuracy by amount (flat percentage points)',
                'dodge_increase'     => 'dodge_increase — increases dodge / evasion chance by amount',
                'agility_increase'   => 'agility_increase — increases speed and evasion stat',
                'max_hp_increase'    => 'max_hp_increase — raises maximum HP by amount (calc_type: percent or number)',
                'max_cp_increase'    => 'max_cp_increase — raises maximum CP by amount (calc_type: percent or number)',
                'damage_reduce'      => 'damage_reduce — reduces all incoming damage by amount (calc_type: percent)',
                'block_damage'       => 'block_damage — blocks a flat amount of damage per hit before HP loss',
                'ignore_dodge'       => 'ignore_dodge — weapon attacks cannot be dodged by the opponent',
                'power_up'           => 'power_up — general outgoing damage multiplier',
                'power_up_by_hp'     => 'power_up_by_hp — damage boost that scales with how much HP the wielder has remaining',
                'protection'         => 'protection — reduces incoming damage by % (similar to damage_reduce)',
                'combustion_increase'=> 'combustion_increase — increases the trigger chance of fire-based effects on hit',
                'dodge_decrease'     => 'dodge_decrease — reduces the opponent\'s dodge/evasion chance passively',
                'attack_reduce_gen_cooldown' => 'attack_reduce_gen_cooldown — each weapon attack reduces the wielder\'s skill cooldowns',
            ],

            'HP Recovery' => [
                'hp_recover'         => 'hp_recover — passive HP regeneration each turn (calc_type: number or percent)',
                'hp_recover_below'   => 'hp_recover_below — HP regen per turn only when HP is below amount % threshold',
                'hp_recover_every_turn' => 'hp_recover_every_turn — guaranteed HP restore each turn (alternate handler)',
                'recover_hp_every_turn' => 'recover_hp_every_turn — HP restored each combat turn (alternate key)',
                'hp_recover_with_attack' => 'hp_recover_with_attack — heals wielder for amount HP on each successful weapon hit',
                'hp_recover_below_cp'=> 'hp_recover_below_cp — HP regen per turn when current CP is below threshold',
                'recover_hp_buff'    => 'recover_hp_buff — HP recovery that activates only when a buff is active on the wielder',
                'recover_hp_debuff'  => 'recover_hp_debuff — HP recovery that activates only when a debuff is active on the wielder',
                'recover_hp_after_critical' => 'recover_hp_after_critical — restores HP after landing a critical hit',
                'recover_hp_after_purify'   => 'recover_hp_after_purify — restores HP when the wielder is purified',
                'recover_hp_when_attacked'  => 'recover_hp_when_attacked — restores HP each time the wielder is hit',
                'recover_hp_by_cp_cost'     => 'recover_hp_by_cp_cost — restores HP proportional to CP spent activating skills',
            ],

            'CP Recovery' => [
                'cp_recover'          => 'cp_recover — passive CP regeneration each turn',
                'cp_recover_below'    => 'cp_recover_below — CP regen per turn when CP is below amount % threshold',
                'cp_recover_with_attack' => 'cp_recover_with_attack — restores CP on each successful weapon hit',
                'recover_cp_buff'     => 'recover_cp_buff — CP recovery active when a buff is on the wielder',
                'recover_cp_debuff'   => 'recover_cp_debuff — CP recovery active when a debuff is on the wielder',
                'recover_cp_after_purify' => 'recover_cp_after_purify — restores CP when the wielder is purified',
                'hp_cp_recover'       => 'hp_cp_recover — restores both HP and CP each turn',
                'recover_hp_cp_buff'  => 'recover_hp_cp_buff — HP+CP recovery while a buff is active',
                'recover_hp_cp_debuff'=> 'recover_hp_cp_debuff — HP+CP recovery while a debuff is active',
            ],

            'Drain Effects (On Hit)' => [
                'drain_cp_with_attack' => 'drain_cp_with_attack — steals amount % of enemy CP on each weapon hit',
                'drain_hp_with_attack' => 'drain_hp_with_attack — steals amount % of enemy HP on each weapon hit (wielder gains it)',
                'absorb_damage_to_cp'  => 'absorb_damage_to_cp — a portion of damage received is converted to CP for the wielder',
                'absorb_damage_to_hp'  => 'absorb_damage_to_hp — a portion of damage received is converted back to HP',
                'cp_absorption'        => 'cp_absorption — absorbs enemy CP passively each turn',
                'damage_to_hp'         => 'damage_to_hp — converts a portion of outgoing weapon damage into HP for the wielder',
                'bloodfeed_attack'     => 'bloodfeed_attack — wielder gains HP proportional to total damage dealt on hit',
            ],

            'Status Infliction (On Hit)' => [
                'inflict_bleeding'    => 'inflict_bleeding — chance to apply Bleeding on each weapon hit',
                'inflict_blind'       => 'inflict_blind — chance to apply Blind on each weapon hit',
                'inflict_burn'        => 'inflict_burn — chance to apply Burn on each weapon hit',
                'inflict_burning'     => 'inflict_burning — chance to apply Burning on each weapon hit',
                'inflict_numb'        => 'inflict_numb — chance to apply Numb on each weapon hit',
                'inflict_petrify'     => 'inflict_petrify — chance to apply Petrify on each weapon hit',
                'inflict_poison'      => 'inflict_poison — chance to apply Poison on each weapon hit',
                'inflict_restriction' => 'inflict_restriction — chance to apply Restriction on each weapon hit',
                'inflict_slow'        => 'inflict_slow — chance to apply Slow on each weapon hit',
                'inflict_weaken'      => 'inflict_weaken — chance to apply Weaken on each weapon hit',
                'stun_when_crit'      => 'stun_when_crit — stuns the target when the wielder lands a critical hit',
                'concentration_when_crit' => 'concentration_when_crit — grants accuracy buff to wielder on critical hit',
                'concentration_when_attacked' => 'concentration_when_attacked — grants accuracy buff when the wielder is hit',
            ],

            'Attacker Retaliation' => [
                'bleeding_attacker'     => 'bleeding_attacker — inflicts Bleeding on whoever attacks the wielder',
                'blind_attacker'        => 'blind_attacker — inflicts Blind on whoever attacks the wielder',
                'burn_attacker'         => 'burn_attacker — inflicts Burn on whoever attacks the wielder',
                'poison_attacker'       => 'poison_attacker — inflicts Poison on whoever attacks the wielder',
                'concentration_attacker'=> 'concentration_attacker — debuffs the attacker\'s accuracy/concentration when they hit',
            ],

            'Direct Passive Status' => [
                'bleeding'  => 'bleeding — opponent has Bleeding passively while you\'re equipped (proc chance = amount %)',
                'burn'      => 'burn — opponent has Burn passively while equipped',
                'burning'   => 'burning — opponent has Burning passively while equipped',
                'poison'    => 'poison — opponent has Poison passively while equipped',
                'sleep'     => 'sleep — passive sleep chance on opponent each turn',
                'stun'      => 'stun — passive stun chance on opponent each turn',
                'numb'      => 'numb — opponent is numbed passively while equipped',
                'slow'      => 'slow — opponent is slowed passively while equipped',
                'weaken'    => 'weaken — opponent is weakened passively while equipped',
                'frozen'    => 'frozen — opponent is frozen passively while equipped',
                'petrify'   => 'petrify — opponent is petrified passively while equipped',
                'dark_curse'    => 'dark_curse — dark curse applied passively while equipped',
                'demonic_curse' => 'demonic_curse — demonic curse applied passively while equipped',
                'chaos'     => 'chaos — chaotic random effects on opponent each turn',
                'darkness'  => 'darkness — darkness field around the opponent passively',
                'restriction' => 'restriction — opponent is restricted passively',
            ],

            'Utility & Skill Modifiers' => [
                'accuracy_up_below_hp'  => 'accuracy_up_below_hp — bonus accuracy when wielder HP is below amount %',
                'accuracy_up_on_focus'  => 'accuracy_up_on_focus — bonus accuracy when the wielder is focused',
                'dodge_damage_bonus'    => 'dodge_damage_bonus — grants bonus damage after the wielder successfully dodges',
                'guard_below_hp'        => 'guard_below_hp — activates a damage reduction guard when HP drops below amount %',
                'cp_shield_weapon'      => 'cp_shield_weapon — uses CP to absorb damage instead of HP (amount = CP consumed per hit)',
                'reduce_cp_consumption' => 'reduce_cp_consumption — flat reduction in CP cost for all skills',
                'reduce_cp_consumption_prc' => 'reduce_cp_consumption_prc — % reduction in CP cost for all skills',
                'reduce_cp_cost'        => 'reduce_cp_cost — reduces the CP cost of the next skill used',
                'double_cp_consumption' => 'double_cp_consumption — doubles the opponent\'s CP cost for skills (debuff on enemy)',
                'purify_increase'       => 'purify_increase — increases the effectiveness of the wielder\'s purify effects',
                'increase_reactive'     => 'increase_reactive — boosts the power of reactive/counter effects',
                'senjutsu_strengthen'   => 'senjutsu_strengthen — boosts the damage and effects of sage mode skills',
                'serene_mind_item'      => 'serene_mind_item — weapon variant of serene mind, reducing debuff duration',
                'attribute_change'      => 'attribute_change — changes the wielder\'s elemental damage attribute',
            ],

            'Advanced & Special' => [
                'rewind'             => 'rewind — reverts the opponent to a previous state (removes recent buffs/heals)',
                'rewind_turn'        => 'rewind_turn — rewinds an entire combat turn worth of actions',
                'transform'          => 'transform — transforms the wielder into a different form',
                'mortal'             => 'mortal — marks the opponent as mortal, enabling instant-kill threshold effects',
                'pet_mortal'         => 'pet_mortal — applies the mortal mark to pet units',
                'instant_kill'       => 'instant_kill — instantly defeats opponent if their HP is below amount % threshold',
                'kill_instant_under' => 'kill_instant_under — instant kill if opponent is under amount % HP (alternate handler)',
                'insta_reduce_max_cp'=> 'insta_reduce_max_cp — permanently reduces opponent\'s maximum CP',
                'insta_reduce_max_hp'=> 'insta_reduce_max_hp — permanently reduces opponent\'s maximum HP',
                'reduce_hp'          => 'reduce_hp — reduces opponent HP by amount (weapon passive variant)',
                'reduceCP'           => 'reduceCP — reduces opponent CP by amount (weapon passive variant)',
                'reduce_cd'          => 'reduce_cd — reduces one or all cooldowns by amount turns',
                'dismantle'          => 'dismantle — removes the opponent\'s equipped item passive effects',
                'disperse'           => 'disperse — strips all active buffs from the opponent',
                'distract'           => 'distract — distracts the opponent, reducing their combat effectiveness',
                'frostbite'          => 'frostbite — cold frostbite damage over time with slow',
                'blaze'              => 'blaze — intense fire damage over time (weapon variant)',
                'hemorrhage'         => 'hemorrhage — heavy bleeding damage over time',
                'infection'          => 'infection — spreading disease effect',
                'plague'             => 'plague — area-of-effect disease damage',
                'internal_injury'    => 'internal_injury — internal chakra damage per turn (weapon variant)',
            ],
        ];
    }
}