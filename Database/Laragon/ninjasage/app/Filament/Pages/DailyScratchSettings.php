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

class DailyScratchSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;
    protected static ?string $navigationLabel = 'Daily Scratch';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Daily Scratch Settings';
    protected string $view = 'filament.pages.daily-scratch-settings';
    protected static ?int $navigationSort = 9;

    /**
     * Regular reward pool — flat array of RewardGrantService-compatible strings.
     *
     * Examples:
     *   gold_50000        → grant gold
     *   tokens_15         → grant tokens
     *   xp_2%             → grant 2% of level XP
     *   tp_15             → grant TP
     *   material_01:5     → grant 5× material_01
     *   wpn_602           → grant weapon (qty 1)
     *   pet_itikura       → grant pet (skipped if already owned)
     *   skill_656         → grant skill (skipped if already owned)
     *
     * Pets and skills in this pool are automatically treated as "rare" — they
     * are excluded from common draws once owned.
     *
     * Grand prize pool — awarded every ~100–500 scratches (pity system).
     * Both pools are written to GameConfig['scratch'] AND gamedata.json 'scratch'
     * node so the client icons stay in sync.
     */
    public array $rewards     = [];
    public array $grand_prize = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        // Prefer GameConfig (server-of-record); fall back to gamedata.json node
        $config = GameConfig::get('scratch', []);

        if (empty($config)) {
            $config = $this->loadGamedataNode();
        }

        foreach ((array) ($config['rewards'] ?? $config) as $r) {
            $this->rewards[] = ['reward_id' => (string) $r];
        }

        foreach ((array) ($config['grand_prize'] ?? []) as $r) {
            $this->grand_prize[] = ['reward_id' => (string) $r];
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Regular Reward Pool')
                ->description(
                    'Items drawn on each scratch. Pets and skills are automatically treated as rare '
                    . '(excluded once owned). '
                    . 'Use RewardGrantService format: gold_50000 · tokens_15 · xp_2% · tp_10 · '
                    . 'material_01:5 · wpn_602 · pet_itikura · skill_656'
                )
                ->schema([
                    Forms\Components\Repeater::make('rewards')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('reward_id')
                                ->label('Reward ID')
                                ->placeholder('e.g. tokens_15  /  material_01:5  /  pet_itikura')
                                ->required()
                                ->extraInputAttributes(['class' => 'font-mono']),
                        ])
                        ->addActionLabel('+ Add Reward')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): string => $state['reward_id'] ?? 'New Reward')
                        ->defaultItems(0),
                ]),

            Section::make('Grand Prize Pool')
                ->description(
                    'Awarded by the pity system: guaranteed after ~500 scratches, with increasing '
                    . 'probability starting at 100. Uses the same reward format as the regular pool.'
                )
                ->schema([
                    Forms\Components\Repeater::make('grand_prize')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('reward_id')
                                ->label('Reward ID')
                                ->placeholder('e.g. tokens_2000')
                                ->required()
                                ->extraInputAttributes(['class' => 'font-mono']),
                        ])
                        ->addActionLabel('+ Add Grand Prize')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): string => $state['reward_id'] ?? 'New Reward')
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
        $rewards = array_values(array_filter(
            array_map(fn ($r) => trim($r['reward_id'] ?? ''), $this->rewards)
        ));

        $grandPrize = array_values(array_filter(
            array_map(fn ($r) => trim($r['reward_id'] ?? ''), $this->grand_prize)
        ));

        $config = [
            'rewards'     => $rewards,
            'grand_prize' => $grandPrize,
        ];

        // 1. Write GameConfig (server reward selection)
        GameConfig::set('scratch', $config);

        // 2. Write gamedata.json scratch node (client reward icon display)
        $this->writeGamedataNode($config);

        // 3. Recompile gamedata.bin
        $this->writeBin();

        Notification::make()
            ->title('Daily Scratch saved (' . count($rewards) . ' rewards, ' . count($grandPrize) . ' grand prizes).')
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
            if (($node['id'] ?? '') === 'scratch') {
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
            if (($node['id'] ?? '') === 'scratch') {
                $node['data'] = $data;
                $found        = true;
                break;
            }
        }
        unset($node);

        if (!$found) {
            $nodes[] = ['id' => 'scratch', 'data' => $data];
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
