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

class EudemonGardenSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?string $navigationLabel = 'Eudemon Garden';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Eudemon Garden Settings';
    protected string $view = 'filament.pages.eudemon-garden-settings';
    protected static ?int $navigationSort = 7;

    public int $default_tries = 3;

    /**
     * Unified boss list. Each entry:
     *  name           string   Display name
     *  lvl            int      Level requirement
     *  rank           int      1=S 2=A 3=B 4=C  (client rankMC frame)
     *  bg             string   Battle background ID (e.g. mission_155)
     *  desc           string   Description text shown in panel
     *  enemy_ids      array    [{enemy_id}]  — enemy IDs loaded into battle
     *  gold           int      Gold reward
     *  xp             int      XP reward
     *  display_rewards array   [{item_id}]  — item icons shown in the panel
     *  server_rewards  array   [{item_id, rate, min, max, unique, owned_rate}]
     */
    public array $bosses = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $gamedataNode = $this->loadGamedataNode();   // client display
        $serverConfig = GameConfig::get('eudemon', []); // server rewards

        $this->default_tries = (int) ($serverConfig['default_tries'] ?? 3);

        $clientBosses = $gamedataNode['bosses'] ?? [];
        $serverBosses = $serverConfig['bosses'] ?? [];

        foreach ($clientBosses as $idx => $cb) {
            $sb = $serverBosses[$idx] ?? [];

            // Enemy IDs: stored as flat array in gamedata.json
            $enemyIds = array_map(
                fn ($eid) => ['enemy_id' => $eid],
                (array) ($cb['id'] ?? [])
            );

            // Display rewards: flat array of item_id strings
            $displayRewards = array_map(
                fn ($r) => ['item_id' => $r],
                (array) ($cb['rewards'] ?? [])
            );

            // Server rewards: array of objects
            $serverRewards = array_map(function ($r) {
                if (is_string($r)) {
                    return ['item_id' => $r, 'rate' => 100, 'min' => 1, 'max' => 1, 'unique' => false, 'owned_rate' => 0];
                }
                return [
                    'item_id'    => $r['id'] ?? '',
                    'rate'       => (float) ($r['rate'] ?? 100),
                    'min'        => (int) ($r['min'] ?? 1),
                    'max'        => (int) ($r['max'] ?? 1),
                    'unique'     => (bool) ($r['unique'] ?? false),
                    'owned_rate' => (float) ($r['owned_rate'] ?? 0),
                ];
            }, (array) ($sb['rewards'] ?? []));

            $this->bosses[] = [
                'name'            => $cb['name'] ?? $sb['name'] ?? '',
                'lvl'             => (int) ($cb['lvl'] ?? $sb['lvl'] ?? 1),
                'rank'            => (int) ($cb['rank'] ?? 4),
                'bg'              => $cb['bg'] ?? 'mission_155',
                'desc'            => $cb['desc'] ?? '',
                'enemy_ids'       => $enemyIds,
                'gold'            => (int) ($cb['gold'] ?? $sb['gold'] ?? 0),
                'xp'              => (int) ($cb['xp'] ?? $sb['xp'] ?? 0),
                'display_rewards' => $displayRewards,
                'server_rewards'  => $serverRewards,
            ];
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Global Settings')->schema([
                Forms\Components\TextInput::make('default_tries')
                    ->label('Default Daily Tries Per Boss')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->default(3)
                    ->helperText('How many attempts each player gets per boss per day. Resets at midnight.')
                    ->required(),
            ])->columns(2),

            Section::make('Bosses')
                ->description('Each boss appears as a row in the Eudemon Garden panel. Order matters.')
                ->schema([
                    Forms\Components\Repeater::make('bosses')
                        ->label('')
                        ->schema([

                            // ── Core info ──────────────────────────────────
                            Grid::make(3)->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Boss Name')
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('lvl')
                                    ->label('Level Requirement')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Select::make('rank')
                                    ->label('Rank (display)')
                                    ->options([4 => 'C', 3 => 'B', 2 => 'A', 1 => 'S'])
                                    ->default(4)
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                            Grid::make(2)->schema([
                                Forms\Components\TextInput::make('bg')
                                    ->label('Battle Background ID')
                                    ->placeholder('e.g. mission_155')
                                    ->helperText('Background asset used in the battle scene.')
                                    ->extraInputAttributes(['class' => 'font-mono'])
                                    ->required()
                                    ->columnSpan(1),

                                Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('gold')
                                        ->label('Gold Reward')
                                        ->numeric()->minValue(0)->required(),

                                    Forms\Components\TextInput::make('xp')
                                        ->label('XP Reward')
                                        ->numeric()->minValue(0)->required(),
                                ])->columnSpan(1),
                            ]),

                            Forms\Components\Textarea::make('desc')
                                ->label('Description')
                                ->rows(2)
                                ->helperText('Shown in the panel when the boss is selected.'),

                            // ── Enemy IDs ──────────────────────────────────
                            Section::make('Enemy IDs')
                                ->description('The enemy/NPC IDs loaded into the battle (e.g. ene_460). Multi-enemy bosses have more than one.')
                                ->compact()
                                ->schema([
                                    Forms\Components\Repeater::make('enemy_ids')
                                        ->label('')
                                        ->schema([
                                            Forms\Components\TextInput::make('enemy_id')
                                                ->label('Enemy ID')
                                                ->placeholder('e.g. ene_460')
                                                ->required()
                                                ->extraInputAttributes(['class' => 'font-mono']),
                                        ])
                                        ->addActionLabel('+ Add Enemy')
                                        ->reorderableWithDragAndDrop()
                                        ->itemLabel(fn (array $state): string => $state['enemy_id'] ?? 'New Enemy')
                                        ->defaultItems(1)
                                        ->minItems(1),
                                ]),

                            // ── Display Rewards ────────────────────────────
                            Section::make('Display Rewards (client icons)')
                                ->description('Item IDs shown as reward icons in the panel. Does not affect what is actually awarded — use Server Rewards for that.')
                                ->compact()
                                ->schema([
                                    Forms\Components\Repeater::make('display_rewards')
                                        ->label('')
                                        ->schema([
                                            Forms\Components\TextInput::make('item_id')
                                                ->label('Item ID')
                                                ->placeholder('e.g. wpn_1138')
                                                ->required()
                                                ->extraInputAttributes(['class' => 'font-mono']),
                                        ])
                                        ->addActionLabel('+ Add Display Item')
                                        ->reorderableWithDragAndDrop()
                                        ->itemLabel(fn (array $state): string => $state['item_id'] ?? 'New Item')
                                        ->defaultItems(0),
                                ]),

                            // ── Server Rewards ─────────────────────────────
                            Section::make('Server Rewards (actual drop table)')
                                ->description('What is actually awarded when a player wins. Rate is 0–100 (%). Unique items reduce to Owned Rate % once the player owns one.')
                                ->compact()
                                ->schema([
                                    Forms\Components\Repeater::make('server_rewards')
                                        ->label('')
                                        ->schema([
                                            Grid::make(3)->schema([
                                                Forms\Components\TextInput::make('item_id')
                                                    ->label('Item ID')
                                                    ->placeholder('e.g. material_01')
                                                    ->required()
                                                    ->extraInputAttributes(['class' => 'font-mono'])
                                                    ->columnSpan(1),

                                                Forms\Components\TextInput::make('rate')
                                                    ->label('Drop Rate (%)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(100)
                                                    ->required()
                                                    ->columnSpan(1),

                                                Grid::make(2)->schema([
                                                    Forms\Components\TextInput::make('min')
                                                        ->label('Min Qty')
                                                        ->numeric()->minValue(1)->default(1)->required(),

                                                    Forms\Components\TextInput::make('max')
                                                        ->label('Max Qty')
                                                        ->numeric()->minValue(1)->default(1)->required(),
                                                ])->columnSpan(1),
                                            ]),

                                            Grid::make(2)->schema([
                                                Forms\Components\Toggle::make('unique')
                                                    ->label('Unique (owned reduces rate)')
                                                    ->default(false)
                                                    ->live()
                                                    ->columnSpan(1),

                                                Forms\Components\TextInput::make('owned_rate')
                                                    ->label('Owned Drop Rate (%)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->helperText('Rate applied once the player already owns this item.')
                                                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('unique'))
                                                    ->columnSpan(1),
                                            ]),
                                        ])
                                        ->addActionLabel('+ Add Server Reward')
                                        ->reorderableWithDragAndDrop()
                                        ->itemLabel(fn (array $state): string =>
                                            ($state['item_id'] ?? '')
                                                ? ($state['item_id'] . ' @ ' . ($state['rate'] ?? 100) . '%')
                                                : 'New Reward'
                                        )
                                        ->defaultItems(0),
                                ]),
                        ])
                        ->addActionLabel('+ Add Boss')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): string =>
                            ($state['name'] ?? '') ?: 'New Boss'
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
                ->label('Save Settings')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $clientBosses = [];
        $serverBosses = [];

        foreach ($this->bosses as $idx => $b) {
            $name = trim($b['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $enemyIds = array_values(array_filter(
                array_map(fn ($e) => trim($e['enemy_id'] ?? ''), $b['enemy_ids'] ?? [])
            ));

            $displayRewards = array_values(array_filter(
                array_map(fn ($r) => trim($r['item_id'] ?? ''), $b['display_rewards'] ?? [])
            ));

            $serverRewards = [];
            foreach ($b['server_rewards'] ?? [] as $r) {
                $itemId = trim($r['item_id'] ?? '');
                if ($itemId === '') {
                    continue;
                }
                $entry = [
                    'id'   => $itemId,
                    'rate' => min(100, max(0, (float) ($r['rate'] ?? 100))),
                    'min'  => max(1, (int) ($r['min'] ?? 1)),
                    'max'  => max(1, (int) ($r['max'] ?? 1)),
                ];
                if (!empty($r['unique'])) {
                    $entry['unique']     = true;
                    $entry['owned_rate'] = min(100, max(0, (float) ($r['owned_rate'] ?? 0)));
                }
                $serverRewards[] = $entry;
            }

            $lvl  = max(1, (int) ($b['lvl'] ?? 1));
            $gold = max(0, (int) ($b['gold'] ?? 0));
            $xp   = max(0, (int) ($b['xp'] ?? 0));

            // gamedata.json node (client display)
            $clientBosses[] = [
                'id'      => $enemyIds,
                'num'     => $idx,
                'name'    => $name,
                'bg'      => trim($b['bg'] ?? 'mission_155'),
                'lvl'     => (string) $lvl,
                'rank'    => (int) ($b['rank'] ?? 4),
                'desc'    => trim($b['desc'] ?? ''),
                'rewards' => $displayRewards,
                'gold'    => $gold,
                'xp'      => $xp,
            ];

            // GameConfig node (server rewards)
            $gradeMap = [1 => 'S', 2 => 'A', 3 => 'B', 4 => 'C'];
            $serverBosses[] = [
                'id'      => 'boss_' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT),
                'name'    => $name,
                'lvl'     => $lvl,
                'grade'   => $gradeMap[(int) ($b['rank'] ?? 4)] ?? 'C',
                'xp'      => $xp,
                'gold'    => $gold,
                'rewards' => $serverRewards,
            ];
        }

        $defaultTries = max(1, (int) $this->default_tries);

        // 1. Write GameConfig
        GameConfig::set('eudemon', [
            'default_tries' => $defaultTries,
            'bosses'        => $serverBosses,
        ]);

        // 2. Write gamedata.json eudemon node
        $this->writeGamedataNode(['bosses' => $clientBosses]);

        // 3. Recompile gamedata.bin
        $this->writeBin();

        $total = count($clientBosses);

        Notification::make()
            ->title("Eudemon Garden saved ({$total} bosses, {$defaultTries} daily tries).")
            ->success()
            ->send();
    }

    // ── I/O ───────────────────────────────────────────────────────────────────

    private function gamedataPath(): string
    {
        return base_path('public/game_data/gamedata.json');
    }

    private function loadGamedataNode(): array
    {
        $path = $this->gamedataPath();
        if (!file_exists($path)) {
            return [];
        }

        $nodes = json_decode(file_get_contents($path), true);
        if (!is_array($nodes)) {
            return [];
        }

        foreach ($nodes as $node) {
            if (($node['id'] ?? '') === 'eudemon') {
                return (array) ($node['data'] ?? []);
            }
        }

        return [];
    }

    private function writeGamedataNode(array $data): void
    {
        $path  = $this->gamedataPath();
        $nodes = json_decode(file_get_contents($path), true) ?? [];

        $found = false;
        foreach ($nodes as &$node) {
            if (($node['id'] ?? '') === 'eudemon') {
                $node['data'] = $data;
                $found        = true;
                break;
            }
        }
        unset($node);

        if (!$found) {
            $nodes[] = ['id' => 'eudemon', 'data' => $data];
        }

        file_put_contents(
            $path,
            json_encode($nodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function writeBin(): bool
    {
        $json = @file_get_contents($this->gamedataPath());
        if ($json === false) {
            return false;
        }
        $compressed = gzcompress($json, 6);
        if ($compressed === false) {
            return false;
        }
        return (bool) file_put_contents(base_path('public/game_data/gamedata.bin'), $compressed);
    }
}