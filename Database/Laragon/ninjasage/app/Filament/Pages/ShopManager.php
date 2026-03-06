<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ShopManager extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;
    protected static ?string $navigationLabel = 'Shop Manager';
    protected static string|\UnitEnum|null $navigationGroup = 'Shop';
    protected static ?string $title = 'Shop Manager';
    protected string $view = 'filament.pages.shop-manager';
    protected static ?int $navigationSort = 1;

    /**
     * shopData[shopType][category] = [
     *   ['item_id' => 'wpn_01', 'name' => 'Kunai', 'price_gold' => 100, ...],
     *   ...
     * ]
     */
    public array $shopData = [];

    private const SHOP_TYPES = [
        'normal' => 'Normal Shop',
        'pvp'    => 'PvP Shop',
        'clan'   => 'Clan Shop',
        'crew'   => 'Crew Shop',
    ];

    private const CATEGORIES = [
        'weapons'    => ['label' => 'Weapons',     'hint' => 'wpn_01, wpn_02 …'],
        'backs'      => ['label' => 'Back Items',  'hint' => 'back_01, back_02 …'],
        'sets'       => ['label' => 'Clothing',    'hint' => 'set_01_%s  (%s = gender placeholder, substituted by client)'],
        'hairs'      => ['label' => 'Hairstyles',  'hint' => 'hair_01%s  (%s = gender placeholder)'],
        'accs'       => ['label' => 'Accessories', 'hint' => 'accessory_01 …'],
        'items'      => ['label' => 'Items',       'hint' => 'item_01, material_01 …'],
        'essentials' => ['label' => 'Essentials',  'hint' => 'essential_01 …'],
        'skills'     => ['label' => 'Skills',      'hint' => 'skill_01 …'],
    ];

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $shopItems = $this->loadShopFromGamedata();
        $library   = $this->loadLibraryIndex();

        foreach (array_keys(self::SHOP_TYPES) as $type) {
            foreach (array_keys(self::CATEGORIES) as $cat) {
                $ids  = $shopItems[$type][$cat] ?? [];
                $rows = [];

                foreach ($ids as $itemId) {
                    $lib    = $library[$itemId] ?? [];
                    $rows[] = [
                        'item_id'        => $itemId,
                        'name'           => (string) ($lib['name']           ?? ''),
                        'price_gold'     => (int)    ($lib['price_gold']     ?? 0),
                        'price_tokens'   => (int)    ($lib['price_tokens']   ?? 0),
                        'price_pvp'      => (int)    ($lib['price_pvp']      ?? 0),
                        'price_prestige' => (int)    ($lib['price_prestige'] ?? 0),
                        'price_merit'    => (int)    ($lib['price_merit']    ?? 0),
                    ];
                }

                $this->shopData[$type][$cat] = $rows;
            }
        }
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        $shopTabs = [];

        foreach (self::SHOP_TYPES as $type => $shopLabel) {
            $sections = [];

            foreach (self::CATEGORIES as $cat => $meta) {
                $count   = count($this->shopData[$type][$cat] ?? []);
                $heading = "{$meta['label']} ({$count})";

                $sections[] = Section::make($heading)
                    ->description($meta['hint'])
                    ->collapsible()
                    ->schema([
                        Forms\Components\Repeater::make("shopData.{$type}.{$cat}")
                            ->label('')
                            ->schema([
                                // ── Item identity row ──────────────────────
                                Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('item_id')
                                        ->label('Item ID')
                                        ->placeholder('e.g. wpn_01')
                                        ->required()
                                        ->extraInputAttributes(['class' => 'font-mono'])
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('name')
                                        ->label('Display Name')
                                        ->placeholder('Auto-filled from library — edit to override')
                                        ->maxLength(120)
                                        ->columnSpan(1),
                                ]),

                                // ── Prices row (one field per currency) ────
                                Grid::make(5)->schema([
                                    Forms\Components\TextInput::make('price_gold')
                                        ->label('Gold')
                                        ->helperText('Character gold')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('price_tokens')
                                        ->label('Tokens')
                                        ->helperText('Account tokens')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('price_pvp')
                                        ->label('PvP Points')
                                        ->helperText('Live PvP points')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('price_prestige')
                                        ->label('Prestige')
                                        ->helperText('Character prestige')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('price_merit')
                                        ->label('Merit')
                                        ->helperText('Clan merit points')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->columnSpan(1),
                                ]),
                            ])
                            ->addActionLabel('+ Add Item')
                            ->reorderableWithDragAndDrop()
                            ->itemLabel(fn (array $state): ?string =>
                                ($state['item_id'] ?? '')
                                    ? trim(($state['item_id'] ?? '') . '  ' . ($state['name'] ?? ''))
                                    : 'New Item'
                            )
                            ->defaultItems(0),
                    ]);
            }

            $shopTabs[] = Tabs\Tab::make($shopLabel)->schema($sections);
        }

        return $schema->components([
            Tabs::make('shopTypes')->tabs($shopTabs),
        ]);
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save All Shops')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),
        ];
    }

    // ── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $cleanShop    = [];   // [type][cat] = [ids]
        $priceUpdates = [];   // [item_id]   = [price fields]
        $nameUpdates  = [];   // [item_id]   = display name

        foreach (array_keys(self::SHOP_TYPES) as $type) {
            foreach (array_keys(self::CATEGORIES) as $cat) {
                $rows = array_values($this->shopData[$type][$cat] ?? []);
                $ids  = [];

                foreach ($rows as $row) {
                    $itemId = trim((string) ($row['item_id'] ?? ''));
                    if ($itemId === '') continue;

                    $ids[] = $itemId;

                    // Last write wins when the same item appears in multiple shops/categories
                    $priceUpdates[$itemId] = [
                        'price_gold'     => (int) ($row['price_gold']     ?? 0),
                        'price_tokens'   => (int) ($row['price_tokens']   ?? 0),
                        'price_pvp'      => (int) ($row['price_pvp']      ?? 0),
                        'price_prestige' => (int) ($row['price_prestige'] ?? 0),
                        'price_merit'    => (int) ($row['price_merit']    ?? 0),
                    ];

                    $customName = trim((string) ($row['name'] ?? ''));
                    if ($customName !== '') {
                        $nameUpdates[$itemId] = $customName;
                    }
                }

                $cleanShop[$type][$cat] = $ids;
            }
        }

        $ok = $this->writeGamedata($cleanShop);
        if (!$ok) {
            Notification::make()->title('Failed to write gamedata.json')->danger()->send();
            return;
        }

        $ok = $this->writePricesToLibrary($priceUpdates, $nameUpdates);
        if (!$ok) {
            Notification::make()->title('Failed to write library.json')->danger()->send();
            return;
        }

        // Recompress JSON → .bin so the game client picks up the changes on next load
        $this->writeBin($this->gamedataPath(), 'gamedata');
        $this->writeBin($this->libraryPath(), 'library');


        Notification::make()
            ->title('Saved — ' . count($priceUpdates) . ' items updated across all shops')
            ->success()
            ->send();
    }

    // ── File helpers ─────────────────────────────────────────────────────────

    private function gamedataPath(): string
    {
        return base_path('public/game_data/gamedata.json');
    }

    private function libraryPath(): string
    {
        return base_path('public/game_data/library.json');
    }

    private function binPath(string $name): string
    {
        return base_path('public/game_data/' . $name . '.bin');
    }

    /**
     * Compresses a JSON file with zlib and writes it as a .bin file for the game client.
     * The game client downloads these from public/game_data/ (served at /game_data/).
     */
    private function writeBin(string $jsonPath, string $binName): bool
    {
        $json = @file_get_contents($jsonPath);
        if ($json === false) return false;

        $compressed = gzcompress($json, 6);
        if ($compressed === false) return false;

        return (bool) file_put_contents($this->binPath($binName), $compressed);
    }

    /** Returns shop data array: [type][category] => [item_id, ...] */
    private function loadShopFromGamedata(): array
    {
        $path = $this->gamedataPath();
        if (!file_exists($path)) return [];

        $raw   = json_decode(file_get_contents($path), true) ?? [];
        $entry = collect($raw)->firstWhere('id', 'shop');
        return $entry['data'] ?? [];
    }

    /** Returns library items indexed by 'id'. */
    private function loadLibraryIndex(): array
    {
        $path = $this->libraryPath();
        if (!file_exists($path)) return [];

        $items = json_decode(file_get_contents($path), true) ?? [];
        $index = [];
        foreach ($items as $item) {
            if (isset($item['id'])) {
                $index[$item['id']] = $item;
            }
        }
        return $index;
    }

    /** Overwrites the shop section in gamedata.json. Returns true on success. */
    private function writeGamedata(array $cleanShop): bool
    {
        $path     = $this->gamedataPath();
        $gamedata = json_decode(file_get_contents($path), true) ?? [];

        foreach ($gamedata as &$entry) {
            if (($entry['id'] ?? null) !== 'shop') continue;
            foreach (array_keys(self::SHOP_TYPES) as $type) {
                foreach (array_keys(self::CATEGORIES) as $cat) {
                    $entry['data'][$type][$cat] = $cleanShop[$type][$cat] ?? [];
                }
            }
            break;
        }
        unset($entry);

        return (bool) file_put_contents(
            $path,
            json_encode($gamedata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Updates price fields (and optionally names) for shop items in library.json.
     * Only modifies entries that already exist in the library; new IDs are ignored.
     * Returns true on success.
     */
    private function writePricesToLibrary(array $priceUpdates, array $nameUpdates): bool
    {
        $path  = $this->libraryPath();
        $items = json_decode(file_get_contents($path), true) ?? [];

        $priceKeys = ['price_gold', 'price_tokens', 'price_pvp', 'price_prestige', 'price_merit'];

        foreach ($items as &$item) {
            $id = $item['id'] ?? null;
            if ($id === null) continue;

            if (isset($priceUpdates[$id])) {
                foreach ($priceKeys as $key) {
                    $item[$key] = $priceUpdates[$id][$key];
                }
            }

            if (isset($nameUpdates[$id]) && $nameUpdates[$id] !== '') {
                $item['name'] = $nameUpdates[$id];
            }
        }
        unset($item);

        // Write compact JSON to keep library.json small (client doesn't need pretty-print)
        return (bool) file_put_contents(
            $path,
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}