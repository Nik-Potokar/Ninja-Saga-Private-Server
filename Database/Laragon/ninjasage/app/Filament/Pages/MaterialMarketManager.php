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

class MaterialMarketManager extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;
    protected static ?string $navigationLabel = 'Material Market';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Material Market Manager';
    protected string $view = 'filament.pages.material-market-manager';
    protected static ?int $navigationSort = 6;

    /**
     * recipes[prefix] = [
     *   [
     *     'item_id'      => 'wpn_609',
     *     'available'    => true,
     *     'requirements' => [['material_id' => 'material_01', 'qty' => 1], ...],
     *   ],
     *   ...
     * ]
     */
    public array $recipes = [];

    private const CATEGORIES = [
        'wpn'       => 'Weapon',
        'set'       => 'Set / Clothing',
        'back'      => 'Back Item',
        'accessory' => 'Accessory',
        'hair'      => 'Hairstyle',
        'skill'     => 'Skill',
        'pet'       => 'Pet',
        'material'  => 'Material',
    ];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $rawRecipes = $this->loadRawRecipes();

        foreach (array_keys(self::CATEGORIES) as $prefix) {
            $this->recipes[$prefix] = [];
        }

        foreach ($rawRecipes as $itemId => $recipe) {
            $prefix = explode('_', $itemId)[0];
            if (!array_key_exists($prefix, self::CATEGORIES)) {
                continue;
            }

            $requirements = [];
            foreach ($recipe['materials'] as $idx => $matId) {
                $requirements[] = [
                    'material_id' => $matId,
                    'qty'         => (int) ($recipe['qty'][$idx] ?? 1),
                ];
            }

            $this->recipes[$prefix][] = [
                'item_id'      => $itemId,
                'available'    => ($recipe['end'] ?? null) === 'Available',
                'requirements' => $requirements,
            ];
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (self::CATEGORIES as $prefix => $label) {
            $count    = count($this->recipes[$prefix] ?? []);
            $tabLabel = "{$label} ({$count})";

            $tabs[] = Tabs\Tab::make($tabLabel)->schema([
                Forms\Components\Repeater::make("recipes.{$prefix}")
                    ->label('')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('item_id')
                                ->label('Output Item ID')
                                ->placeholder("e.g. {$prefix}_01")
                                ->helperText('The item the player receives after crafting.')
                                ->required()
                                ->extraInputAttributes(['class' => 'font-mono'])
                                ->columnSpan(1),

                            Forms\Components\Toggle::make('available')
                                ->label('Available in Market')
                                ->helperText('When off, the client shows this recipe as "Unavailable".')
                                ->default(true)
                                ->columnSpan(1),
                        ]),

                        Section::make('Craft Requirements')
                            ->description('Materials the player must consume to craft this item.')
                            ->compact()
                            ->schema([
                                Forms\Components\Repeater::make('requirements')
                                    ->label('')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('material_id')
                                                ->label('Material ID')
                                                ->placeholder('e.g. material_01')
                                                ->helperText('Standard craft materials: material_01–material_06.')
                                                ->required()
                                                ->extraInputAttributes(['class' => 'font-mono'])
                                                ->columnSpan(1),

                                            Forms\Components\TextInput::make('qty')
                                                ->label('Quantity Required')
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->required()
                                                ->columnSpan(1),
                                        ]),
                                    ])
                                    ->addActionLabel('+ Add Material')
                                    ->reorderableWithDragAndDrop()
                                    ->itemLabel(fn (array $state): string =>
                                        ($state['material_id'] ?? '')
                                            ? ($state['material_id'] . ' × ' . ($state['qty'] ?? 1))
                                            : 'New Material'
                                    )
                                    ->defaultItems(1)
                                    ->minItems(1),
                            ]),
                    ])
                    ->addActionLabel("+ Add {$label}")
                    ->reorderableWithDragAndDrop()
                    ->itemLabel(fn (array $state): string => ($state['item_id'] ?? '') ?: 'New Item')
                    ->defaultItems(0),
            ]);
        }

        return $schema->components([
            Tabs::make('categories')->tabs($tabs),
        ]);
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Recipes')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $rawRecipes = [];

        foreach (array_keys(self::CATEGORIES) as $prefix) {
            foreach ($this->recipes[$prefix] ?? [] as $entry) {
                $itemId = trim((string) ($entry['item_id'] ?? ''));
                if ($itemId === '') {
                    continue;
                }

                $materials = [];
                $qtys      = [];

                foreach ($entry['requirements'] ?? [] as $req) {
                    $matId = trim((string) ($req['material_id'] ?? ''));
                    if ($matId === '') {
                        continue;
                    }
                    $materials[] = $matId;
                    $qtys[]      = max(1, (int) ($req['qty'] ?? 1));
                }

                if (empty($materials)) {
                    continue;
                }

                $rawRecipes[$itemId] = [
                    'materials' => $materials,
                    'qty'       => $qtys,
                    'end'       => ($entry['available'] ?? true) ? 'Available' : null,
                ];
            }
        }

        if (!$this->writeRecipes($rawRecipes)) {
            Notification::make()
                ->title('Failed to write MaterialMarketRecipes.php — check file permissions.')
                ->danger()
                ->send();
            return;
        }

        $total = count($rawRecipes);

        Notification::make()
            ->title("Material Market recipes saved ({$total} items).")
            ->success()
            ->send();
    }

    // ── I/O ───────────────────────────────────────────────────────────────────

    private function recipesPath(): string
    {
        return app_path('Services/Amf/MaterialMarketRecipes.php');
    }

    private function loadRawRecipes(): array
    {
        $path = $this->recipesPath();
        if (!file_exists($path)) {
            return [];
        }

        $recipes = require $path;
        return is_array($recipes) ? $recipes : [];
    }

    private function writeRecipes(array $rawRecipes): bool
    {
        $lines   = [];
        $lines[] = "<?php\n";
        $lines[] = "\n";
        $lines[] = "/**\n";
        $lines[] = " * Material Market forge recipes — managed via the admin panel (MaterialMarketManager).\n";
        $lines[] = " *\n";
        $lines[] = " * Format:\n";
        $lines[] = " *   'output_item_id' => [\n";
        $lines[] = " *       'materials' => ['material_id_1', ...],\n";
        $lines[] = " *       'qty'       => [5, ...],\n";
        $lines[] = " *       'end'       => 'Available', // null = shown as Unavailable in client\n";
        $lines[] = " *   ]\n";
        $lines[] = " *\n";
        $lines[] = " * Item-ID prefix determines the client tab:\n";
        $lines[] = " *   wpn_ → Weapon   set_ → Set   back_ → Back   accessory_ → Accessory\n";
        $lines[] = " *   hair_ → Hair     skill_ → Skill   pet_ → Pet   material_ → Material\n";
        $lines[] = " */\n";
        $lines[] = "return [\n";

        $lastPrefix = null;
        foreach ($rawRecipes as $itemId => $recipe) {
            $prefix = explode('_', $itemId)[0];
            if ($prefix !== $lastPrefix) {
                $sectionLabel = self::CATEGORIES[$prefix] ?? ucfirst($prefix);
                $lines[]      = "\n    // ── {$sectionLabel} ──\n";
                $lastPrefix   = $prefix;
            }

            $mats = implode(', ', array_map(fn ($m) => "'{$m}'", $recipe['materials']));
            $qtys = implode(', ', $recipe['qty']);
            $end  = $recipe['end'] === null ? 'null' : "'{$recipe['end']}'";

            $lines[] = "    '{$itemId}' => [\n";
            $lines[] = "        'materials' => [{$mats}],\n";
            $lines[] = "        'qty'       => [{$qtys}],\n";
            $lines[] = "        'end'       => {$end},\n";
            $lines[] = "    ],\n";
        }

        $lines[] = "];\n";

        return file_put_contents($this->recipesPath(), implode('', $lines)) !== false;
    }
}
