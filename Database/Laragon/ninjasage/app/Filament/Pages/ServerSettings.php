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

class ServerSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Server Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Server Settings';

    protected string $view = 'filament.pages.server-settings';

    // ── Server identity ────────────────────────────────────────────────────────
    public string $server_label  = 'Private Server';
    public string $version_label = 'Version 0.1';

    // ── Version gate ───────────────────────────────────────────────────────────
    public string $required_version = 'Version 0.1';

    // ── Maintenance ────────────────────────────────────────────────────────────
    public bool $maintenance_mode = false;

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $saved = GameConfig::get('version_config', []);

        $this->server_label     = $saved['server_label']     ?? 'Private Server';
        $this->version_label    = $saved['version_label']    ?? 'Version 0.1';
        $this->required_version = $saved['required_version'] ?? 'Version 0.1';
        $this->maintenance_mode = (bool) ($saved['maintenance_mode'] ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Server Identity')
                    ->description('Cosmetic labels shown in the admin panel. Not sent to the client.')
                    ->schema([
                        Forms\Components\TextInput::make('server_label')
                            ->label('Server Name')
                            ->placeholder('Private Server')
                            ->required(),

                        Forms\Components\TextInput::make('version_label')
                            ->label('Version Label')
                            ->placeholder('Version 0.1')
                            ->helperText('Display-only label shown in this panel.')
                            ->required(),
                    ]),

                Section::make('Version Gate')
                    ->description(
                        'The AMF gateway compares the client\'s build_num against the Required Version string below. ' .
                        'Clients that send a different value receive status 2 (outdated) and cannot log in.'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('required_version')
                            ->label('Required Client Version')
                            ->placeholder('Version 0.1')
                            ->helperText(
                                'Must exactly match the build_num string set in the SWF. ' .
                                'Current value: Storage/Character.as → public static var build_num:* = "Version 0.1";'
                            )
                            ->required(),

                        Forms\Components\Placeholder::make('outdated_client_info')
                            ->label('Outdated Client Message')
                            ->content(
                                'The message shown to the player when their client is outdated is hardcoded in the SWF and ' .
                                'cannot be changed from the server.' . "\n\n" .
                                'To change it, edit the following file and recompile:' . "\n" .
                                'Decompiled/CustomClient/scripts/id/ninjasage/tasks/core/VersionCheckTask.as — line 52' . "\n" .
                                'Current text: "Please update your game."' . "\n\n" .
                                'The download button URL is also hardcoded:' . "\n" .
                                'Decompiled/CustomClient/scripts/Panels/Update.as — line 23' . "\n" .
                                'Current URL: https://ninjasage.id/en#downloads'
                            ),
                    ]),

                Section::make('Maintenance Mode')
                    ->description(
                        'When enabled, ALL clients receive status 3 at version check and are blocked from logging in. ' .
                        'The version gate is bypassed — maintenance takes priority.'
                    )
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Enable Maintenance Mode')
                            ->helperText('Blocks all logins immediately. Turn off to reopen the server.')
                            ->inline(false),

                        Forms\Components\Placeholder::make('maintenance_message_info')
                            ->label('Maintenance Message')
                            ->content(
                                'The message shown to the player during maintenance is hardcoded in the SWF and ' .
                                'cannot be changed from the server.' . "\n\n" .
                                'To change it, edit the following file and recompile:' . "\n" .
                                'Decompiled/CustomClient/scripts/id/ninjasage/tasks/core/VersionCheckTask.as — line 60' . "\n" .
                                'Current text: "The game is under active maintenance, please check back later!"'
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
            'server_label'     => ['required', 'string', 'max:100'],
            'version_label'    => ['required', 'string', 'max:50'],
            'required_version' => ['required', 'string', 'max:100'],
            'maintenance_mode' => ['boolean'],
        ]);

        GameConfig::set('version_config', [
            'server_label'     => $this->server_label,
            'version_label'    => $this->version_label,
            'required_version' => $this->required_version,
            'maintenance_mode' => (bool) $this->maintenance_mode,
        ]);

        Notification::make()
            ->title('Server settings saved')
            ->success()
            ->send();
    }
}