<?php

namespace App\Filament\Pages;

use App\Models\Skill;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AcademySettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;
    protected static ?string $navigationLabel = 'Academy Skills';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Academy Skill Lists';
    protected string $view = 'filament.pages.academy-settings';
    protected static ?int $navigationSort = 3;

    // ── Form state — one array per element ───────────────────────────────────

    public array $wind = [];
    public array $fire = [];
    public array $thunder = [];
    public array $earth = [];
    public array $water = [];
    public array $taijutsu = [];
    public array $genjutsu = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $academyIds = $this->loadAcademyFromJson();

        // Load prices from DB in one query
        $allIds = array_merge(...array_values($academyIds));
        $prices = Skill::whereIn('skill_id', $allIds)
            ->get()
            ->keyBy('skill_id')
            ->map(fn ($s) => ['price_gold' => $s->price_gold, 'price_tokens' => $s->price_tokens]);

        foreach (['wind', 'fire', 'thunder', 'earth', 'water', 'taijutsu', 'genjutsu'] as $el) {
            $this->$el = array_map(function (string $id) use ($prices) {
                return [
                    'skill_id'     => $id,
                    'price_gold'   => $prices[$id]['price_gold']   ?? 0,
                    'price_tokens' => $prices[$id]['price_tokens'] ?? 0,
                ];
            }, $academyIds[$el] ?? []);
        }
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Wind (元素: 風)')
                ->icon(Heroicon::OutlinedCloud)
                ->collapsible()
                ->schema([$this->elementRepeater('wind')]),

            Section::make('Fire (元素: 火)')
                ->icon(Heroicon::OutlinedFire)
                ->collapsible()
                ->collapsed()
                ->schema([$this->elementRepeater('fire')]),

            Section::make('Thunder (元素: 雷)')
                ->icon(Heroicon::OutlinedBolt)
                ->collapsible()
                ->collapsed()
                ->schema([$this->elementRepeater('thunder')]),

            Section::make('Earth (元素: 土)')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->collapsible()
                ->collapsed()
                ->schema([$this->elementRepeater('earth')]),

            Section::make('Water (元素: 水)')
                ->icon(Heroicon::OutlinedBeaker)
                ->collapsible()
                ->collapsed()
                ->schema([$this->elementRepeater('water')]),

            Section::make('Taijutsu (体術)')
                ->icon(Heroicon::OutlinedHandRaised)
                ->collapsible()
                ->collapsed()
                ->schema([$this->elementRepeater('taijutsu')]),

            Section::make('Genjutsu (幻術)')
                ->icon(Heroicon::OutlinedEye)
                ->collapsible()
                ->collapsed()
                ->schema([$this->elementRepeater('genjutsu')]),
        ]);
    }

    private function elementRepeater(string $element): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($element)
            ->label('')
            ->schema([
                Grid::make(3)->schema([
                    Forms\Components\Select::make('skill_id')
                        ->label('Skill')
                        ->options(
                            Skill::orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($s) => [$s->skill_id => "{$s->name} ({$s->skill_id})"])
                        )
                        ->searchable()
                        ->required()
                        ->columnSpan(1)
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if (!$state) return;
                            $skill = Skill::where('skill_id', $state)->first();
                            if ($skill) {
                                $set('price_gold',   $skill->price_gold);
                                $set('price_tokens', $skill->price_tokens);
                            }
                        }),

                    Forms\Components\TextInput::make('price_gold')
                        ->label('Price (Gold)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('price_tokens')
                        ->label('Price (Tokens)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->columnSpan(1),
                ]),
            ])
            ->defaultItems(0)
            ->addActionLabel('Add Skill')
            ->reorderableWithDragAndDrop()
            ->itemLabel(function (array $state): ?string {
                if (empty($state['skill_id'])) return null;
                $skill = Skill::where('skill_id', $state['skill_id'])->first();
                return $skill ? "{$skill->name} ({$state['skill_id']})" : $state['skill_id'];
            });
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Lists')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action('save'),
        ];
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $elements = ['wind', 'fire', 'thunder', 'earth', 'water', 'taijutsu', 'genjutsu'];

        $rules = [];
        foreach ($elements as $el) {
            $rules[$el]                   = ['array'];
            $rules["{$el}.*.skill_id"]    = ['required', 'string', 'exists:skills,skill_id'];
            $rules["{$el}.*.price_gold"]  = ['required', 'integer', 'min:0'];
            $rules["{$el}.*.price_tokens"] = ['required', 'integer', 'min:0'];
        }

        $this->validate($rules);

        // Collect all price overrides keyed by skill_id
        $priceOverrides = [];
        $academy = [];
        foreach ($elements as $el) {
            $academy[$el] = [];
            foreach ($this->$el as $row) {
                $skillId = trim($row['skill_id']);
                $academy[$el][] = $skillId;

                $priceOverrides[$skillId] = [
                    'price_gold'   => (int) $row['price_gold'],
                    'price_tokens' => (int) $row['price_tokens'],
                ];

                // Persist prices to the skills table
                Skill::where('skill_id', $skillId)->update($priceOverrides[$skillId]);
            }
            $academy[$el] = array_values($academy[$el]);
        }

        // 1. Update gamedata.json and recompile to gamedata.bin so the client sees new skills
        $this->writeAcademyToJson($academy);
        $this->writeBin($this->gamedataPath(), 'gamedata');

        // 2. Update skills.json prices and recompile to skills.bin so the client reads correct prices
        $skillsPath = base_path('public/game_data/skills.json');
        if (file_exists($skillsPath) && !empty($priceOverrides)) {
            $skills = json_decode(file_get_contents($skillsPath), true) ?? [];
            foreach ($skills as &$entry) {
                $id = $entry['id'] ?? null;
                if ($id && isset($priceOverrides[$id])) {
                    $entry['price_gold']   = $priceOverrides[$id]['price_gold'];
                    $entry['price_tokens'] = $priceOverrides[$id]['price_tokens'];
                }
            }
            unset($entry);
            file_put_contents(
                $skillsPath,
                json_encode($skills, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $this->writeBin($skillsPath, 'skills');
        }

        Notification::make()
            ->title('Academy skill lists and prices saved.')
            ->success()
            ->send();
    }

    private function writeBin(string $jsonPath, string $binName): bool
    {
        $json = @file_get_contents($jsonPath);
        if ($json === false) return false;
        $compressed = gzcompress($json, 6);
        if ($compressed === false) return false;
        return (bool) file_put_contents(base_path('public/game_data/' . $binName . '.bin'), $compressed);
    }

    // ── JSON I/O ─────────────────────────────────────────────────────────────

    private function gamedataPath(): string
    {
        return base_path('public/game_data/gamedata.json');
    }

    /** @return array<string, string[]> */
    private function loadAcademyFromJson(): array
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
            if (($node['id'] ?? '') === 'academy') {
                return (array) ($node['data'] ?? []);
            }
        }

        return [];
    }

    private function writeAcademyToJson(array $academy): void
    {
        $path  = $this->gamedataPath();
        $nodes = json_decode(file_get_contents($path), true);

        $found = false;
        foreach ($nodes as &$node) {
            if (($node['id'] ?? '') === 'academy') {
                $node['data'] = $academy;
                $found = true;
                break;
            }
        }
        unset($node);

        if (!$found) {
            $nodes[] = ['id' => 'academy', 'data' => $academy];
        }

        file_put_contents(
            $path,
            json_encode($nodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}