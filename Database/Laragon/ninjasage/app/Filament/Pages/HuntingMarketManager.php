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

class HuntingMarketManager extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;
    protected static ?string $navigationLabel = 'Hunting Market';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Hunting Market Manager';
    protected string $view = 'filament.pages.hunting-market-manager';
    protected static ?int $navigationSort = 4;

    /**
     * recipes[prefix] = [
     *   ['item_id' => 'wpn_81', 'requirements' => [['material_id' => 'material_509', 'qty' => 10], ...]],
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
                'requirements' => $requirements,
            ];
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (self::CATEGORIES as $prefix => $label) {
            $count  = count($this->recipes[$prefix] ?? []);
            $tabLabel = "{$label} ({$count})";

            $tabs[] = Tabs\Tab::make($tabLabel)->schema([
                Forms\Components\Repeater::make("recipes.{$prefix}")
                    ->label('')
                    ->schema([
                        Grid::make(1)->schema([
                            Forms\Components\TextInput::make('item_id')
                                ->label('Output Item ID')
                                ->placeholder("e.g. {$prefix}_01")
                                ->helperText('The item the player receives after forging.')
                                ->required()
                                ->extraInputAttributes(['class' => 'font-mono']),
                        ]),

                        Section::make('Craft Requirements')
                            ->description('Materials the player must consume to forge this item. Up to 12 are displayed client-side.')
                            ->compact()
                            ->schema([
                                Forms\Components\Repeater::make('requirements')
                                    ->label('')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('material_id')
                                                ->label('Material ID')
                                                ->placeholder('e.g. material_509')
                                                ->helperText('Any item prefix works: material_, wpn_, skill_, …')
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
                    ->itemLabel(fn (array $state): string =>
                        ($state['item_id'] ?? '') ?: 'New Item'
                    )
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
                ];
            }
        }

        if (!$this->writeRecipes($rawRecipes)) {
            Notification::make()
                ->title('Failed to write HuntingForgeRecipes.php — check file permissions.')
                ->danger()
                ->send();
            return;
        }

        $total = count($rawRecipes);

        Notification::make()
            ->title("Hunting Market recipes saved ({$total} items).")
            ->success()
            ->send();
    }

    // ── I/O ───────────────────────────────────────────────────────────────────

    private function recipesPath(): string
    {
        return app_path('Services/Amf/HuntingHouseService/HuntingForgeRecipes.php');
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
        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = "\n";
        $lines[] = "/**\n";
        $lines[] = " * Hunting Market forge recipes — managed via the admin panel (HuntingMarketManager).\n";
        $lines[] = " *\n";
        $lines[] = " * Format:\n";
        $lines[] = " *   'output_item_id' => [\n";
        $lines[] = " *       'materials' => ['material_id_1', 'material_id_2', ...],\n";
        $lines[] = " *       'qty'       => [5, 3, ...],\n";
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
                $lines[] = "\n    // ── {$sectionLabel} ──\n";
                $lastPrefix = $prefix;
            }

            $mats = implode(', ', array_map(fn ($m) => "'{$m}'", $recipe['materials']));
            $qtys = implode(', ', $recipe['qty']);

            $lines[] = "    '{$itemId}' => [\n";
            $lines[] = "        'materials' => [{$mats}],\n";
            $lines[] = "        'qty'       => [{$qtys}],\n";
            $lines[] = "    ],\n";
        }

        $lines[] = "];\n";

        return file_put_contents($this->recipesPath(), implode('', $lines)) !== false;
    }
}
