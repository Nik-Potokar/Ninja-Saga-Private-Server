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

class SpecialDealsSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Special Deals';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Special Deals Management';

    protected string $view = 'filament.pages.special-deals-settings';

    /** @var array<int, array{id: int, name: string, end: string, price: int, items: list<string>}> */
    public array $deals = [];

    public function mount(): void
    {
        $saved = GameConfig::get('special_deals', []);

        // Ensure items sub-array is always a list string
        $this->deals = array_map(function (array $deal) {
            return [
                'id'    => (int)   ($deal['id']    ?? 0),
                'name'  => (string)($deal['name']  ?? ''),
                'end'   => (string)($deal['end']   ?? 'Limited Time'),
                'price' => (int)   ($deal['price'] ?? 0),
                'items' => implode("\n", (array)($deal['items'] ?? [])),
            ];
        }, $saved);
    }

    public function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Special Deal Packages')
                ->description(
                    'Each deal is purchasable by players using tokens. ' .
                    'Items use the format item_id (qty defaults to 1) or item_id:qty — one per line. ' .
                    'Example: wpn_81 or material_509:5. ' .
                    'IDs must exist in the game library. Enable the "Special Deals" side icon in Game Settings.'
                )
                ->schema([
                    Forms\Components\Repeater::make('deals')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('id')
                                ->label('Deal ID')
                                ->helperText('Unique integer ID used when purchasing.')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            Forms\Components\TextInput::make('name')
                                ->label('Display Name')
                                ->helperText('Shown in the game UI (e.g. "Starter Pack").')
                                ->maxLength(80)
                                ->required(),

                            Forms\Components\TextInput::make('end')
                                ->label('End Label')
                                ->helperText('Text shown as the deal duration (e.g. "Limited Time").')
                                ->maxLength(40)
                                ->default('Limited Time'),

                            Forms\Components\TextInput::make('price')
                                ->label('Price (Tokens)')
                                ->helperText('Account tokens charged on purchase.')
                                ->numeric()
                                ->minValue(0)
                                ->required(),

                            Forms\Components\Textarea::make('items')
                                ->label('Reward Items (one per line)')
                                ->helperText('Format: item_id  or  item_id:quantity. E.g.  wpn_81  or  material_509:5')
                                ->rows(4)
                                ->required(),
                        ])
                        ->columns(4) // <--- this replaces Grid::make(4)
                        ->addActionLabel('Add Deal')
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?: null)
                        ->defaultItems(0),
                ]),
        ]);
}

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Deals')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'deals'             => ['array'],
            'deals.*.id'        => ['required', 'integer', 'min:1'],
            'deals.*.name'      => ['required', 'string', 'max:80'],
            'deals.*.end'       => ['nullable', 'string', 'max:40'],
            'deals.*.price'     => ['required', 'integer', 'min:0'],
            'deals.*.items'     => ['required', 'string'],
        ]);

        $normalized = [];
        foreach ($this->deals as $deal) {
            // Convert textarea (newline-separated) back to array, stripping blanks
            $itemLines = array_values(array_filter(
                array_map('trim', explode("\n", (string)($deal['items'] ?? '')))
            ));

            $normalized[] = [
                'id'    => (int)   $deal['id'],
                'name'  => (string)$deal['name'],
                'end'   => (string)($deal['end'] ?: 'Limited Time'),
                'price' => (int)   $deal['price'],
                'items' => $itemLines,
            ];
        }

        GameConfig::set('special_deals', $normalized);

        Notification::make()
            ->title('Special deals saved')
            ->success()
            ->send();
    }
}
