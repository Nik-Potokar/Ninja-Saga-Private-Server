<?php

namespace App\Filament\Pages;

use App\Models\GameConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HuntingHouseSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;
    protected static ?string $navigationLabel = 'Hunting House';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Hunting House Settings';
    protected string $view = 'filament.pages.hunting-house-settings';
    protected static ?int $navigationSort = 10;

    // ── State ─────────────────────────────────────────────────────────────────

    /**
     * General settings loaded from GameConfig['hunting_house_settings'].
     * {daily_claim_free, daily_claim_premium, material_price}
     */
    public int $daily_claim_free    = 5;
    public int $daily_claim_premium = 10;
    public int $material_price      = 5;

    /**
     * Zone rewards loaded from GameConfig['hunting_house_zone_rewards'].
     * Each zone is a flat array of item IDs — duplicates mean +1 quantity.
     * zoneN_rewards = [['item_id' => 'material_509'], ...]
     */
    public array $zone1_rewards = [];
    public array $zone2_rewards = [];
    public array $zone3_rewards = [];
    public array $zone4_rewards = [];
    public array $zone5_rewards = [];

    /**
     * Forge recipes loaded from GameConfig['hunting_house_forge_recipes'].
     * Each recipe: {output_item, materials: [{mat_id, qty}]}
     */
    public array $recipes = [];

    // ── Defaults (mirrors the hardcoded service defaults) ─────────────────────

    private const ZONE_DEFAULTS = [
        1 => ['material_509'],
        2 => ['material_509', 'material_509'],
        3 => ['material_509'],
        4 => ['material_509', 'material_509', 'material_509'],
        5 => ['material_509', 'material_509', 'material_509', 'material_509'],
    ];

    private const RECIPE_DEFAULTS = [
        'wpn_81' => ['materials' => ['material_509'], 'qty' => [10]],
        'wpn_82' => ['materials' => ['material_509'], 'qty' => [15]],
        'wpn_83' => ['materials' => ['material_509'], 'qty' => [20]],
        'wpn_84' => ['materials' => ['material_509'], 'qty' => [25]],
        'wpn_85' => ['materials' => ['material_509'], 'qty' => [30]],
        'wpn_86' => ['materials' => ['material_509'], 'qty' => [35]],
    ];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        // General settings
        $settings = GameConfig::get('hunting_house_settings', []);
        $this->daily_claim_free    = (int) ($settings['daily_claim_free']    ?? 5);
        $this->daily_claim_premium = (int) ($settings['daily_claim_premium'] ?? 10);
        $this->material_price      = (int) ($settings['material_price']      ?? 5);

        // Zone rewards
        $zoneConfig = GameConfig::get('hunting_house_zone_rewards', null);
        $zoneData   = (is_array($zoneConfig) && !empty($zoneConfig))
            ? $zoneConfig
            : self::ZONE_DEFAULTS;

        for ($z = 1; $z <= 5; $z++) {
            $flat      = (array) ($zoneData[$z] ?? $zoneData[(string) $z] ?? []);
            $prop      = "zone{$z}_rewards";
            $this->$prop = array_map(fn ($id) => ['item_id' => (string) $id], $flat);
        }

        // Forge recipes
        $recipesConfig = GameConfig::get('hunting_house_forge_recipes', null);
        $rawRecipes    = (is_array($recipesConfig) && !empty($recipesConfig))
            ? $recipesConfig
            : self::RECIPE_DEFAULTS;

        foreach ($rawRecipes as $outputItem => $recipe) {
            $materials = (array) ($recipe['materials'] ?? []);
            $qtys      = (array) ($recipe['qty']       ?? []);
            $mats      = [];
            foreach ($materials as $idx => $matId) {
                $mats[] = ['mat_id' => (string) $matId, 'qty' => (int) ($qtys[$idx] ?? 1)];
            }
            $this->recipes[] = ['output_item' => (string) $outputItem, 'materials' => $mats];
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            // ── General Settings ─────────────────────────────────────────────
            Section::make('General Settings')
                ->description('Daily Kari Badge claim rewards and badge purchase price.')
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('daily_claim_free')
                            ->label('Free Daily Claim (badges)')
                            ->helperText('Kari Badges granted to free accounts per day.')
                            ->numeric()->minValue(0)->required(),

                        Forms\Components\TextInput::make('daily_claim_premium')
                            ->label('Premium Daily Claim (badges)')
                            ->helperText('Kari Badges granted to premium accounts per day.')
                            ->numeric()->minValue(0)->required(),

                        Forms\Components\TextInput::make('material_price')
                            ->label('Badge Purchase Price (tokens)')
                            ->helperText('Tokens charged per Kari Badge purchased via the shop.')
                            ->numeric()->minValue(1)->required(),
                    ]),
                ]),

            // ── Zone Rewards ─────────────────────────────────────────────────
            Section::make('Zone Rewards')
                ->description(
                    'Materials granted after finishing a hunt in each zone. '
                    . 'Add the same item multiple times to grant more than one on each hunt.'
                )
                ->schema([
                    Grid::make(1)->schema([
                        $this->makeZoneRepeater(1, 'Zone 1 — Easy (1 boss)'),
                        $this->makeZoneRepeater(2, 'Zone 2 — Hard (2 bosses)'),
                        $this->makeZoneRepeater(3, 'Zone 3 — Easy (1 boss)'),
                        $this->makeZoneRepeater(4, 'Zone 4 — Hard (2 bosses)'),
                        $this->makeZoneRepeater(5, 'Zone 5 — Hard (2 bosses)'),
                    ]),
                ]),

            // ── Forge Recipes ─────────────────────────────────────────────────
            Section::make('Forge Recipes')
                ->description(
                    'Items craftable in the Hunting Market. '
                    . 'Each recipe has an output item and one or more material costs. '
                    . 'The client groups items by prefix: wpn_ / back_ / set_ / accessory_ / hair_ / skill_ / pet_ / material_'
                )
                ->schema([
                    Forms\Components\Repeater::make('recipes')
                        ->label('')
                        ->schema([
                            Grid::make(1)->schema([
                                Forms\Components\TextInput::make('output_item')
                                    ->label('Output Item ID')
                                    ->placeholder('e.g. wpn_81')
                                    ->required()
                                    ->extraInputAttributes(['class' => 'font-mono']),

                                Forms\Components\Repeater::make('materials')
                                    ->label('Required Materials')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('mat_id')
                                                ->label('Material ID')
                                                ->placeholder('e.g. material_509')
                                                ->required()
                                                ->extraInputAttributes(['class' => 'font-mono'])
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('qty')
                                                ->label('Quantity Required')
                                                ->numeric()->minValue(1)->default(10)->required()
                                                ->columnSpan(1),
                                        ]),
                                    ])
                                    ->addActionLabel('+ Add Material')
                                    ->minItems(1)
                                    ->defaultItems(1),
                            ]),
                        ])
                        ->addActionLabel('+ Add Recipe')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): string => $state['output_item'] ?? 'New Recipe')
                        ->defaultItems(0),
                ]),
        ]);
    }

    private function makeZoneRepeater(int $zone, string $label): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make("zone{$zone}_rewards")
            ->label($label)
            ->schema([
                Forms\Components\TextInput::make('item_id')
                    ->label('Item ID')
                    ->placeholder('e.g. material_509')
                    ->required()
                    ->extraInputAttributes(['class' => 'font-mono']),
            ])
            ->addActionLabel('+ Add Item Drop')
            ->reorderableWithDragAndDrop(false)
            ->defaultItems(0);
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        // 1. General settings
        GameConfig::set('hunting_house_settings', [
            'daily_claim_free'    => max(0, (int) $this->daily_claim_free),
            'daily_claim_premium' => max(0, (int) $this->daily_claim_premium),
            'material_price'      => max(1, (int) $this->material_price),
        ]);

        // 2. Zone rewards — store as {zone_id: [item_ids...]}
        $zoneData = [];
        for ($z = 1; $z <= 5; $z++) {
            $prop      = "zone{$z}_rewards";
            $zoneData[$z] = array_values(array_filter(
                array_map(fn ($row) => trim($row['item_id'] ?? ''), $this->$prop)
            ));
        }
        GameConfig::set('hunting_house_zone_rewards', $zoneData);

        // 3. Forge recipes — store as {output_item: {materials: [...], qty: [...]}}
        $recipesData = [];
        foreach ($this->recipes as $row) {
            $outputItem = trim($row['output_item'] ?? '');
            if ($outputItem === '') {
                continue;
            }
            $mats = [];
            $qtys = [];
            foreach ((array) ($row['materials'] ?? []) as $matRow) {
                $matId = trim($matRow['mat_id'] ?? '');
                $qty   = max(1, (int) ($matRow['qty'] ?? 1));
                if ($matId !== '') {
                    $mats[] = $matId;
                    $qtys[] = $qty;
                }
            }
            if (!empty($mats)) {
                $recipesData[$outputItem] = ['materials' => $mats, 'qty' => $qtys];
            }
        }
        GameConfig::set('hunting_house_forge_recipes', $recipesData);

        Notification::make()
            ->title('Hunting House settings saved (' . count($recipesData) . ' forge recipes).')
            ->success()
            ->send();
    }
}
