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

class AnnouncementSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Announcements';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $title = 'In-Game Announcements';

    protected string $view = 'filament.pages.announcement-settings';

    public bool   $enabled = false;
    public string $text    = '';

    public function mount(): void
    {
        $saved = GameConfig::get('announcements', []);

        $this->enabled = (bool)($saved['enabled'] ?? false);
        $this->text    = (string)($saved['text'] ?? '');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('News Panel Announcement')
                    ->description(
                        'When enabled this text is shown in the News panel that pops up on the Character Select screen. ' .
                        'Basic HTML tags are supported (e.g. <b>, <font color="#ff0000">, <br>).'
                    )
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Show announcement')
                            ->helperText('Turn this off to hide the News panel entirely.')
                            ->inline(false),

                        Forms\Components\Textarea::make('text')
                            ->label('Announcement text')
                            ->helperText('Supports basic HTML. Use \\r\\n or <br> for line breaks.')
                            ->rows(8)
                            ->placeholder("Welcome to the server!\r\n<font color=\"#ffff00\">Season 1 has started!</font>"),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'text'    => ['nullable', 'string', 'max:4000'],
        ]);

        GameConfig::set('announcements', [
            'enabled' => (bool)$this->enabled,
            'text'    => (string)$this->text,
        ]);

        Notification::make()
            ->title('Announcement saved')
            ->success()
            ->send();
    }
}
