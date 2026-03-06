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
use Livewire\Attributes\Computed;

class PrestigeSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Prestige & PvP Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Prestige & PvP Settings';

    protected string $view = 'filament.pages.prestige-settings';

    // Clan battle fields
    public int $clan_win_char_prestige    = 5;
    public int $clan_lose_char_prestige   = 1;
    public int $clan_win_clan_reputation  = 10;
    public int $clan_lose_clan_reputation = 10;

    // Crew battle fields
    public int $crew_win_prestige  = 10;
    public int $crew_lose_prestige = 5;

    // Live PvP fields
    public int $pvp_points_win   = 10;
    public int $pvp_points_lose  = 0;
    public int $pvp_prestige_win = 10;
    public int $trophy_k_factor  = 32;
    public int $trophy_floor     = 0;

    public function mount(): void
    {
        $saved = GameConfig::get('prestige_settings', []);

        $this->clan_win_char_prestige    = $saved['clan_win_char_prestige']    ?? 5;
        $this->clan_lose_char_prestige   = $saved['clan_lose_char_prestige']   ?? 1;
        $this->clan_win_clan_reputation  = $saved['clan_win_clan_reputation']  ?? 10;
        $this->clan_lose_clan_reputation = $saved['clan_lose_clan_reputation'] ?? 10;
        $this->crew_win_prestige         = $saved['crew_win_prestige']         ?? 10;
        $this->crew_lose_prestige        = $saved['crew_lose_prestige']        ?? 5;

        $pvpSaved = GameConfig::get('pvp_settings', []);
        $this->pvp_points_win   = $pvpSaved['pvp_points_win']   ?? 10;
        $this->pvp_points_lose  = $pvpSaved['pvp_points_lose']  ?? 0;
        $this->pvp_prestige_win = $pvpSaved['pvp_prestige_win'] ?? 10;
        $this->trophy_k_factor  = $pvpSaved['trophy_k_factor']  ?? 32;
        $this->trophy_floor     = $pvpSaved['trophy_floor']     ?? 0;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clan Battle Prestige')
                    ->description('Controls how much prestige is awarded or deducted when a clan battle finishes.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('clan_win_char_prestige')
                            ->label('Character Prestige on Win')
                            ->helperText('Personal prestige added to the attacking player\'s character when they win a clan battle.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('clan_lose_char_prestige')
                            ->label('Character Prestige on Lose')
                            ->helperText('Personal prestige added to the attacking player\'s character when they lose a clan battle.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('clan_win_clan_reputation')
                            ->label('Clan Reputation Gained on Win')
                            ->helperText('Reputation added to the attacking clan when they win.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('clan_lose_clan_reputation')
                            ->label('Defender Clan Reputation Lost on Defeat')
                            ->helperText('Reputation deducted from the defending clan when the attacker wins.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),

                Section::make('Crew Battle Prestige')
                    ->description('Controls how much prestige is awarded or deducted when a crew castle battle finishes.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('crew_win_prestige')
                            ->label('Crew Prestige Gained on Win')
                            ->helperText('Prestige added to the attacking crew when they capture a castle.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('crew_lose_prestige')
                            ->label('Defender Crew Prestige Lost on Defeat')
                            ->helperText('Prestige deducted from the defending crew when the attacker captures their castle.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),

                Section::make('Live PvP Battle Rewards')
                    ->description('Controls points, prestige, and trophy changes for live PvP ranked battles. Changes take effect within 60 seconds (no server restart needed).')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('pvp_points_win')
                            ->label('PvP Points on Win')
                            ->helperText('PvP points added to the winner\'s balance after a ranked battle.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('pvp_points_lose')
                            ->label('PvP Points Deducted on Loss')
                            ->helperText('PvP points deducted from the loser\'s balance (floored at 0). Set 0 to disable.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('pvp_prestige_win')
                            ->label('Prestige on Win')
                            ->helperText('Personal prestige added to the winner\'s character after a ranked battle.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('trophy_k_factor')
                            ->label('Trophy Elo K-Factor')
                            ->helperText('Elo K-factor controlling trophy volatility. Higher = bigger swings. Default: 32.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('trophy_floor')
                            ->label('Trophy Floor')
                            ->helperText('Minimum trophies a player can fall to. Default: 0.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'clan_win_char_prestige'    => ['required', 'integer', 'min:0'],
            'clan_lose_char_prestige'   => ['required', 'integer', 'min:0'],
            'clan_win_clan_reputation'  => ['required', 'integer', 'min:0'],
            'clan_lose_clan_reputation' => ['required', 'integer', 'min:0'],
            'crew_win_prestige'         => ['required', 'integer', 'min:0'],
            'crew_lose_prestige'        => ['required', 'integer', 'min:0'],
            'pvp_points_win'            => ['required', 'integer', 'min:0'],
            'pvp_points_lose'           => ['required', 'integer', 'min:0'],
            'pvp_prestige_win'          => ['required', 'integer', 'min:0'],
            'trophy_k_factor'           => ['required', 'integer', 'min:1'],
            'trophy_floor'              => ['required', 'integer', 'min:0'],
        ]);

        GameConfig::set('prestige_settings', [
            'clan_win_char_prestige'    => (int) $this->clan_win_char_prestige,
            'clan_lose_char_prestige'   => (int) $this->clan_lose_char_prestige,
            'clan_win_clan_reputation'  => (int) $this->clan_win_clan_reputation,
            'clan_lose_clan_reputation' => (int) $this->clan_lose_clan_reputation,
            'crew_win_prestige'         => (int) $this->crew_win_prestige,
            'crew_lose_prestige'        => (int) $this->crew_lose_prestige,
        ]);

        GameConfig::set('pvp_settings', [
            'pvp_points_win'   => (int) $this->pvp_points_win,
            'pvp_points_lose'  => (int) $this->pvp_points_lose,
            'pvp_prestige_win' => (int) $this->pvp_prestige_win,
            'trophy_k_factor'  => (int) $this->trophy_k_factor,
            'trophy_floor'     => (int) $this->trophy_floor,
        ]);

        Notification::make()
            ->title('Prestige & PvP settings saved')
            ->success()
            ->send();
    }
}