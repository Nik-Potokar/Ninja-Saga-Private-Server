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

class GameSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    protected static ?string $navigationLabel = 'Game Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Game Settings';

    protected string $view = 'filament.pages.game-settings';

    // Side icon toggles
    public bool $special_deals_visible = false;

    public function mount(): void
    {
        $saved = GameConfig::get('game_settings', []);

        $this->special_deals_visible = (bool) ($saved['special_deals_visible'] ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Side Panel Icons')
                    ->description(
                        'Controls which shortcut icons appear on the right-hand side of the game HUD. ' .
                        'Changes take effect the next time a player logs in or refreshes their character data.'
                    )
                    ->schema([
                        Forms\Components\Toggle::make('special_deals_visible')
                            ->label('Special Deals icon')
                            ->helperText(
                                'Shows the "Special Deals" button on the HUD side panel. ' .
                                'Make sure deals are configured under Settings → Special Deals before enabling.'
                            )
                            ->inline(false),

                        Forms\Components\Placeholder::make('limited_store_note')
                            ->label('Limited Store icon')
                            ->content(
                                'The Limited Store (Mysterious Market) button is controlled automatically by the ' .
                                'active Mysterious Market record. Go to Mysterious Markets → create/edit a market ' .
                                'and set it active with a future end date to show this icon.'
                            ),

                        Forms\Components\Placeholder::make('event_menu_note')
                            ->label('Event Menu icon')
                            ->content(
                                'The Event Menu button is always visible in the current Flash client and cannot ' .
                                'be toggled server-side without a client update.'
                            ),

                        Forms\Components\Placeholder::make('design_contest_note')
                            ->label('Design Contest / Contest Shop icon')
                            ->content(
                                'The Contest Shop button is always visible in the current Flash client and cannot ' .
                                'be toggled server-side without a client update.'
                            ),
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
            'special_deals_visible' => ['boolean'],
        ]);

        $existing = GameConfig::get('game_settings', []);

        GameConfig::set('game_settings', array_merge($existing, [
            'special_deals_visible' => (bool) $this->special_deals_visible,
        ]));

        Notification::make()
            ->title('Game settings saved')
            ->success()
            ->send();
    }
}
