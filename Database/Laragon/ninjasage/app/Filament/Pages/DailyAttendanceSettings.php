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

class DailyAttendanceSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Daily Attendance';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Daily Attendance Rewards';

    protected string $view = 'filament.pages.daily-attendance-settings';

    /** @var array<int, array{id: string, price: int, item: string}> */
    public array $rewards = [];

    public function mount(): void
    {
        $saved = GameConfig::get('attendance_rewards', []);

        $this->rewards = array_map(fn (array $r) => [
            'id'    => (string)($r['id']    ?? ''),
            'price' => (int)   ($r['price'] ?? 1),
            'item'  => (string)($r['item']  ?? ''),
        ], (array) $saved);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reward Slots')
                ->description(
                    'Up to 6 reward slots displayed in the Daily Stamp panel. ' .
                    'Each slot unlocks when the player reaches its required login-day count within the current calendar month. ' .
                    'Order matters — slot 0 is the first icon in the panel, slot 5 is the last. ' .
                    'Reward string formats: gold_50000 · tokens_100 · xp_percent_50 · tp_200 · ss_50 · material_874:5 · item_xxx · skill_%s_xxx'
                )
                ->schema([
                    Forms\Components\Repeater::make('rewards')
                        ->label('')
                        ->schema([
                            Grid::make(3)->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('Reward ID')
                                    ->helperText('Unique string sent when the player claims (e.g. tier_1).')
                                    ->maxLength(40)
                                    ->required(),

                                Forms\Components\TextInput::make('price')
                                    ->label('Days Required')
                                    ->helperText('Login days needed this month to unlock this slot (1–31).')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required(),

                                Forms\Components\TextInput::make('item')
                                    ->label('Reward String')
                                    ->helperText('e.g. gold_50000 · tokens_100 · material_874:5 · item_xxx')
                                    ->maxLength(100)
                                    ->required(),
                            ]),
                        ])
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->addActionLabel('Add Reward Slot')
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => ($state['id'] ?? '')
                            ? "Slot: {$state['id']} — {$state['item']} (requires {$state['price']} days)"
                            : null
                        ),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Rewards')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'rewards'         => ['array', 'max:6'],
            'rewards.*.id'    => ['required', 'string', 'max:40'],
            'rewards.*.price' => ['required', 'integer', 'min:1', 'max:31'],
            'rewards.*.item'  => ['required', 'string', 'max:100'],
        ]);

        $normalized = array_values(array_map(fn (array $r) => [
            'id'    => (string) $r['id'],
            'price' => (int)    $r['price'],
            'item'  => trim((string) $r['item']),
        ], $this->rewards));

        GameConfig::set('attendance_rewards', $normalized);

        Notification::make()
            ->title('Attendance rewards saved')
            ->success()
            ->send();
    }
}
