<?php

namespace App\Filament\Pages;

use App\Models\GameConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ExoticPackageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Exotic Packages';

    protected static string |\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Exotic Package Management';

    protected string $view = 'filament.pages.exotic-package-settings';

    /** @var list<array{key: string, name: string, price: int, items: string}> */
    public array $packages = [];

    // Known package keys that the client SWFs reference.
    // Each key maps to a *Set.as class loaded via loadExternalSwfPanel().
    public const KNOWN_KEYS = [
        'spiritoforient' => 'Spirit of Orient Set',
        'necromancer'    => 'Necromancer Set',
        'tearsofkingdom' => 'Tears of Kingdom Set',
        'ancientruins'   => 'Ancient Ruins Set',
        'mechbuser'      => 'Mechbuser Set',
        'firebeast'      => 'Fire Beast Set',
        'nightingale'    => 'Nightingale Set',
        'monolith'       => 'Monolith Set',
        'hanyaoni'       => 'Hanyaoni Set',
        'desertdweller'  => 'Desert Dweller Set',
    ];

    public function mount(): void
    {
        $saved = GameConfig::get('exotic_packages', []);

        $this->packages = array_map(function (array $pkg) {
            return [
                'key'   => (string) ($pkg['key']   ?? ''),
                'name'  => (string) ($pkg['name']  ?? ''),
                'price' => (int)    ($pkg['price'] ?? 0),
                'items' => implode("\n", (array) ($pkg['items'] ?? [])),
            ];
        }, $saved);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Exotic Package Configuration')
                ->description(
                    'Each package corresponds to a *Set panel in the game (e.g. Spirit of Orient Set). ' .
                    'The Key must exactly match the identifier the client SWF uses. ' .
                    'Items use item_id or item_id:qty format, one per line (e.g. wpn_219 or set_001:1). ' .
                    'Packages are purchased with account tokens.'
                )
                ->schema([
                    Forms\Components\Repeater::make('packages')
                        ->label('')
                        ->columns(3)
                        ->schema([
                            Forms\Components\Select::make('key')
                                ->label('Package Key')
                                ->helperText('Must match the key in the *Set.as client file.')
                                ->options(self::KNOWN_KEYS)
                                ->required(),

                            Forms\Components\TextInput::make('name')
                                ->label('Display Name')
                                ->helperText('Shown as titleTxt in the set panel.')
                                ->maxLength(80)
                                ->required(),

                            Forms\Components\TextInput::make('price')
                                ->label('Price (Tokens)')
                                ->helperText('Account tokens charged on purchase.')
                                ->numeric()
                                ->minValue(0)
                                ->required(),

                            Forms\Components\Textarea::make('items')
                                ->label('Reward Items (one per line)')
                                ->helperText('Format: item_id  or  item_id:quantity — up to 6 icons are shown in the UI.')
                                ->rows(4)
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('Add Package')
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string =>
                            isset($state['key']) && isset(self::KNOWN_KEYS[$state['key']])
                                ? self::KNOWN_KEYS[$state['key']] . ($state['name'] ? ' — ' . $state['name'] : '')
                                : ($state['name'] ?: null)
                        )
                        ->defaultItems(0),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Packages')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'packages'         => ['array'],
            'packages.*.key'   => ['required', 'string'],
            'packages.*.name'  => ['required', 'string', 'max:80'],
            'packages.*.price' => ['required', 'integer', 'min:0'],
            'packages.*.items' => ['required', 'string'],
        ]);

        $normalized = [];
        foreach ($this->packages as $pkg) {
            $itemLines = array_values(array_filter(
                array_map('trim', explode("\n", (string) ($pkg['items'] ?? '')))
            ));

            $normalized[] = [
                'key'   => (string) $pkg['key'],
                'name'  => (string) $pkg['name'],
                'price' => (int)    $pkg['price'],
                'items' => $itemLines,
            ];
        }

        GameConfig::set('exotic_packages', $normalized);

        Notification::make()
            ->title('Exotic packages saved')
            ->success()
            ->send();
    }
}