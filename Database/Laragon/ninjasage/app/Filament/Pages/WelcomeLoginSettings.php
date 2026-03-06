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

class WelcomeLoginSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;
    protected static ?string $navigationLabel = 'Welcome Login';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Welcome Login Rewards';
    protected string $view = 'filament.pages.welcome-login-settings';
    protected static ?int $navigationSort = 2;

    // ── Form State ────────────────────────────────────────────────────────────

    /** Exactly 7 reward strings — one per day slot (index 0 = day 1 … index 6 = day 7) */
    public string $day1 = '';
    public string $day2 = '';
    public string $day3 = '';
    public string $day4 = '';
    public string $day5 = '';
    public string $day6 = '';
    public string $day7 = '';

    // ── Defaults (mirrors the original hardcoded values) ──────────────────────

    private const DEFAULTS = [
        'gold_250000',
        'item_45:5',
        'essential_01:1',
        'essential_05:5',
        'essential_12:1',
        'tokens_500',
        'skill_2158',
    ];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $saved = GameConfig::get('welcome_login_rewards', self::DEFAULTS);
        $saved = array_values((array) $saved);

        // Pad / fill to exactly 7 slots
        for ($i = 0; $i < 7; $i++) {
            $saved[$i] = $saved[$i] ?? (self::DEFAULTS[$i] ?? '');
        }

        $this->day1 = (string) $saved[0];
        $this->day2 = (string) $saved[1];
        $this->day3 = (string) $saved[2];
        $this->day4 = (string) $saved[3];
        $this->day5 = (string) $saved[4];
        $this->day6 = (string) $saved[5];
        $this->day7 = (string) $saved[6];
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        $formatHint =
            'Reward string formats: ' .
            'gold_250000 · tokens_500 · tp_200 · ss_50 · ' .
            'xp_10000 · xp_percent_50 · ' .
            'item_45:5 · essential_01:1 · material_874:3 · back_12:1 · ' .
            'skill_2158 · pet_petId';

        return $schema->components([

            Section::make('7-Day Reward Slots')
                ->description(
                    'One reward per day. Day 1 is available on the first login; Day 7 is available after 7 calendar days since account creation. ' .
                    'Players can claim each slot only once. Changes take effect immediately for any player who has not yet claimed that slot. ' .
                    $formatHint
                )
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('day1')
                            ->label('Day 1')
                            ->helperText('Claimed on the day the character is created (login day 1).')
                            ->placeholder('e.g. gold_250000')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('day2')
                            ->label('Day 2')
                            ->placeholder('e.g. item_45:5')
                            ->required()
                            ->maxLength(100),
                    ]),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('day3')
                            ->label('Day 3')
                            ->placeholder('e.g. essential_01:1')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('day4')
                            ->label('Day 4')
                            ->placeholder('e.g. essential_05:5')
                            ->required()
                            ->maxLength(100),
                    ]),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('day5')
                            ->label('Day 5')
                            ->placeholder('e.g. essential_12:1')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('day6')
                            ->label('Day 6')
                            ->placeholder('e.g. tokens_500')
                            ->required()
                            ->maxLength(100),
                    ]),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('day7')
                            ->label('Day 7')
                            ->helperText('The final / most valuable reward, available on day 7.')
                            ->placeholder('e.g. skill_2158')
                            ->required()
                            ->maxLength(100),
                    ]),
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
                ->action('save'),

            Action::make('resetDefaults')
                ->label('Reset to Defaults')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset to original defaults?')
                ->modalDescription('This will restore the original 7-day rewards that were hardcoded at launch. Any custom changes will be overwritten.')
                ->action('resetDefaults'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'day1' => ['required', 'string', 'max:100'],
            'day2' => ['required', 'string', 'max:100'],
            'day3' => ['required', 'string', 'max:100'],
            'day4' => ['required', 'string', 'max:100'],
            'day5' => ['required', 'string', 'max:100'],
            'day6' => ['required', 'string', 'max:100'],
            'day7' => ['required', 'string', 'max:100'],
        ]);

        $rewards = [
            trim($this->day1),
            trim($this->day2),
            trim($this->day3),
            trim($this->day4),
            trim($this->day5),
            trim($this->day6),
            trim($this->day7),
        ];

        GameConfig::set('welcome_login_rewards', $rewards);

        Notification::make()
            ->title('Welcome login rewards saved.')
            ->success()
            ->send();
    }

    // ── Reset to hardcoded defaults ───────────────────────────────────────────

    public function resetDefaults(): void
    {
        GameConfig::set('welcome_login_rewards', self::DEFAULTS);

        $this->day1 = self::DEFAULTS[0];
        $this->day2 = self::DEFAULTS[1];
        $this->day3 = self::DEFAULTS[2];
        $this->day4 = self::DEFAULTS[3];
        $this->day5 = self::DEFAULTS[4];
        $this->day6 = self::DEFAULTS[5];
        $this->day7 = self::DEFAULTS[6];

        Notification::make()
            ->title('Welcome login rewards reset to original defaults.')
            ->success()
            ->send();
    }
}
