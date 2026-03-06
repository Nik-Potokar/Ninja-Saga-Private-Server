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

class ClanSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Clan Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Clan Settings';

    protected string $view = 'filament.pages.clan-settings';

    // Stamina fields
    public int $initial_stamina      = 5;
    public int $max_stamina_cap      = 200;
    public int $stamina_upgrade_cost = 500;
    public int $stamina_upgrade_step = 50;

    // Max members fields
    public int $default_max_members     = 20;
    public int $max_members_cap         = 45;
    public int $increase_members_cost   = 10;

    public function mount(): void
    {
        $saved = GameConfig::get('clan_settings', []);

        $this->initial_stamina        = $saved['initial_stamina']        ?? 5;
        $this->max_stamina_cap        = $saved['max_stamina_cap']        ?? 200;
        $this->stamina_upgrade_cost   = $saved['stamina_upgrade_cost']   ?? 500;
        $this->stamina_upgrade_step   = $saved['stamina_upgrade_step']   ?? 50;
        $this->default_max_members    = $saved['default_max_members']    ?? 20;
        $this->max_members_cap        = $saved['max_members_cap']        ?? 45;
        $this->increase_members_cost  = $saved['increase_members_cost']  ?? 10;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clan Stamina')
                    ->description('Controls stamina values for clan members. Changes apply to new members and future upgrades.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('initial_stamina')
                            ->label('Initial Stamina')
                            ->helperText('Stamina (and max_stamina) assigned to a player when they first join a clan.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('max_stamina_cap')
                            ->label('Max Stamina Cap')
                            ->helperText('Hard ceiling for max_stamina upgrades. The Flash client hides the upgrade button once this is reached.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('stamina_upgrade_cost')
                            ->label('Stamina Upgrade Cost (Tokens)')
                            ->helperText('Account tokens deducted per stamina upgrade.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\TextInput::make('stamina_upgrade_step')
                            ->label('Stamina Upgrade Step')
                            ->helperText('How much max_stamina increases per upgrade (e.g. 50 → 5 to 55 → 105…).')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ]),

                Section::make('Clan Max Members')
                    ->description('Controls how many members a clan can hold and how the "Increase Members" feature works.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('default_max_members')
                            ->label('Default Max Members (new clans)')
                            ->helperText('max_members value assigned when a new clan is created.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('max_members_cap')
                            ->label('Max Members Cap')
                            ->helperText('Absolute ceiling for the "Increase Members" feature. The Flash client UI hard-codes 45; raise only if you have a custom client.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\TextInput::make('increase_members_cost')
                            ->label('Cost per New Slot (Clan Tokens)')
                            ->helperText('Clan tokens deducted per new member slot. Cost = new_max × this value (matches Flash: new_max × 10).')
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
            'initial_stamina'       => ['required', 'integer', 'min:1'],
            'max_stamina_cap'       => ['required', 'integer', 'min:1'],
            'stamina_upgrade_cost'  => ['required', 'integer', 'min:0'],
            'stamina_upgrade_step'  => ['required', 'integer', 'min:1'],
            'default_max_members'   => ['required', 'integer', 'min:1'],
            'max_members_cap'       => ['required', 'integer', 'min:1'],
            'increase_members_cost' => ['required', 'integer', 'min:0'],
        ]);

        GameConfig::set('clan_settings', [
            'initial_stamina'       => (int) $this->initial_stamina,
            'max_stamina_cap'       => (int) $this->max_stamina_cap,
            'stamina_upgrade_cost'  => (int) $this->stamina_upgrade_cost,
            'stamina_upgrade_step'  => (int) $this->stamina_upgrade_step,
            'default_max_members'   => (int) $this->default_max_members,
            'max_members_cap'       => (int) $this->max_members_cap,
            'increase_members_cost' => (int) $this->increase_members_cost,
        ]);

        Notification::make()
            ->title('Clan settings saved')
            ->success()
            ->send();
    }
}
