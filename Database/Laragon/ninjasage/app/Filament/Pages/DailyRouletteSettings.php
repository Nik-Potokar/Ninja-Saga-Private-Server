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

class DailyRouletteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static ?string $navigationLabel = 'Daily Roulette';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Daily Roulette Settings';
    protected string $view = 'filament.pages.daily-roulette-settings';
    protected static ?int $navigationSort = 8;

    /**
     * Reward pool loaded from GameConfig['roulette_rewards'].
     *
     * Stored in the DB as a flat array of strings: ['gold_5000', 'tokens_100', 'xp_2000', ...]
     * The roulette wheel lands randomly on one entry (by array index); the consecutive-day
     * multiplier is then applied server-side before granting.
     *
     * Only gold / tokens / xp types are supported — the multiplier logic splits on '_' and
     * reads parts[0] (type) and parts[1] (integer value).
     *
     * rewards[] = ['type' => 'gold'|'tokens'|'xp', 'amount' => int]
     */
    public array $rewards = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $raw = GameConfig::get('roulette_rewards', []);

        foreach ((array) $raw as $rewardStr) {
            if (!is_string($rewardStr)) {
                continue;
            }
            $parts = explode('_', $rewardStr, 2);
            $type  = $parts[0] ?? 'gold';
            $amount = (int) preg_replace('/\D/', '', $parts[1] ?? '0');

            $this->rewards[] = ['type' => $type, 'amount' => $amount];
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reward Pool')
                ->description(
                    'Each entry is one slot on the roulette wheel. The wheel lands on a random slot; '
                    . 'the consecutive-day multiplier (day 1–7) is then applied to the amount before it is granted. '
                    . 'Only Gold, Tokens, and XP are supported reward types.'
                )
                ->schema([
                    Forms\Components\Repeater::make('rewards')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Reward Type')
                                    ->options([
                                        'gold'   => 'Gold',
                                        'tokens' => 'Tokens',
                                        'xp'     => 'XP',
                                    ])
                                    ->default('gold')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Base Amount')
                                    ->helperText('Multiplied by the consecutive-day streak (1–7) before granting.')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1000)
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                        ])
                        ->addActionLabel('+ Add Reward Slot')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): string =>
                            ($state['type'] ?? '')
                                ? ucfirst($state['type']) . ' × ' . number_format((int) ($state['amount'] ?? 0))
                                : 'New Slot'
                        )
                        ->defaultItems(0),
                ]),
        ]);
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Rewards')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $flat = [];

        foreach ($this->rewards as $row) {
            $type   = trim($row['type'] ?? '');
            $amount = max(1, (int) ($row['amount'] ?? 1));

            if (!in_array($type, ['gold', 'tokens', 'xp'], true)) {
                continue;
            }

            $flat[] = $type . '_' . $amount;
        }

        GameConfig::set('roulette_rewards', $flat);

        $total = count($flat);

        Notification::make()
            ->title("Daily Roulette saved ({$total} reward slots).")
            ->success()
            ->send();
    }
}
