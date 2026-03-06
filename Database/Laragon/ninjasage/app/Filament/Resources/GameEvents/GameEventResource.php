<?php

namespace App\Filament\Resources\GameEvents;

use App\Filament\Resources\GameEvents\Pages;
use App\Models\GameEvent;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class GameEventResource extends Resource
{
    protected static ?string $model = GameEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    public static function getNavigationGroup(): ?string
    {
        return 'Game Events';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Info')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->options([
                                'seasonal'  => 'Seasonal – time-limited banner event (Yuki Onna, Confronting Death…)',
                                'permanent' => 'Permanent – always-available event mode (Monster Hunter, Dragon Hunt…)',
                                'feature'   => 'Feature – utility/gacha tab (Leaderboard, Daily Gacha…)',
                                'package'   => 'Package/Deal – purchasable content bundle',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('panel')
                            ->label('Panel Name')
                            ->helperText('The Flash SWF class the client loads when this event is opened. Only panels with a backend service will function fully. New panels can be added in config/game_events.php.')
                            ->options(config('game_events.panels'))
                            ->searchable()
                            ->live()
                            ->native(false),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon Key')
                            ->helperText('Icon identifier sent to the client for permanent/feature events (e.g. "monster_hunter").')
                            ->maxLength(100)
                            ->visible(fn ($get) => in_array($get('type'), ['permanent', 'feature'])),

                        Forms\Components\TextInput::make('date')
                            ->label('Date Label')
                            ->helperText('Displayed date string for seasonal/package events, e.g. "Dec 25 – Jan 10".')
                            ->maxLength(100)
                            ->visible(fn ($get) => in_array($get('type'), ['seasonal', 'package'])),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first. Events with equal sort_order are ordered by id.'),

                        Forms\Components\Toggle::make('active')
                            ->required()
                            ->default(true)
                            ->helperText('Can be set manually, or controlled automatically by the schedule below.'),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Auto-activate at')
                            ->helperText('Leave blank to control active status manually.')
                            ->native(false)
                            ->seconds(false)
                            ->timezone('UTC'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Auto-deactivate at')
                            ->helperText('Leave blank for no automatic deactivation.')
                            ->native(false)
                            ->seconds(false)
                            ->timezone('UTC')
                            ->after('starts_at'),

                        Forms\Components\Toggle::make('inside')
                            ->label('Show Inside Panel')
                            ->helperText('Feature events only: renders inside the events panel instead of opening an external window.')
                            ->visible(fn ($get) => $get('type') === 'feature'),
                    ]),

                Section::make('Seasonal Event Details')
                    ->visible(fn ($get) => $get('type') === 'seasonal')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')
                            ->label('Banner Image URL')
                            ->maxLength(512)
                            ->helperText('Full URL to the banner image shown in the seasonal event slider.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->maxLength(65535),
                    ]),

                Section::make('Monster Hunter Settings')
                    ->visible(fn ($get) => $get('panel') === 'MonsterHunter')
                    ->schema([
                        Forms\Components\TextInput::make('mh_boss_id')
                            ->label('Enemy Boss ID')
                            ->helperText('The enemy SWF to fight, without the .swf extension (e.g. ene_2005). The file must exist in Client/enemy/.')
                            ->maxLength(50)
                            ->columnSpanFull(),
                    ]),

                Section::make('Gacha Pool Editor')
                    ->description('Structured editor for the gacha pool configuration. Use this instead of raw JSON for DragonGacha and ChristmasGacha.')
                    ->visible(fn ($get) => in_array($get('panel'), config('game_events.gacha_panels', [])))
                    ->schema([
                        Section::make('Pool Weights')
                            ->description('Percentage chance for each tier. The three values must add up to 100.')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('gacha_weight_top')
                                    ->label('Top tier %')
                                    ->numeric()
                                    ->default(5)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),

                                Forms\Components\TextInput::make('gacha_weight_mid')
                                    ->label('Mid tier %')
                                    ->numeric()
                                    ->default(25)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),

                                Forms\Components\TextInput::make('gacha_weight_common')
                                    ->label('Common tier %')
                                    ->numeric()
                                    ->default(70)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%'),
                            ]),

                        Forms\Components\Repeater::make('gacha_draws')
                            ->label('Draw Types')
                            ->helperText('Each row defines one draw mode. coin_cost and token_cost are optional (leave blank to omit).')
                            ->columns(4)
                            ->addActionLabel('Add draw type')
                            ->defaultItems(0)
                            ->schema([
                                Forms\Components\TextInput::make('draw_key')
                                    ->label('Key')
                                    ->placeholder('e.g. normal, advanced')
                                    ->required(),

                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('coin_cost')
                                    ->label('Coin cost')
                                    ->numeric()
                                    ->placeholder('—'),

                                Forms\Components\TextInput::make('token_cost')
                                    ->label('Token cost')
                                    ->numeric()
                                    ->placeholder('—'),
                            ]),

                        Section::make('Pool Items')
                            ->description('One item ID per line. Gender-variable IDs use %s (e.g. hair_223_%s).')
                            ->columns(3)
                            ->schema([
                                Forms\Components\Textarea::make('gacha_pool_top')
                                    ->label('Top tier items')
                                    ->rows(10)
                                    ->placeholder("wpn_1121\npet_goldclowndragon\ntokens_2000"),

                                Forms\Components\Textarea::make('gacha_pool_mid')
                                    ->label('Mid tier items')
                                    ->rows(10)
                                    ->placeholder("hair_223_%s\nset_839_%s\nmaterial_200"),

                                Forms\Components\Textarea::make('gacha_pool_common')
                                    ->label('Common tier items')
                                    ->rows(10)
                                    ->placeholder("material_773\nitem_49\ngold_"),
                            ]),
                    ]),

                Section::make('Event Configuration (JSON)')
                    ->description('Paste valid JSON to configure the server-side behaviour of this event. Each event type has a different expected structure — see the helper text below for a template.')
                    ->visible(fn ($get) => !in_array($get('panel'), config('game_events.gacha_panels', [])))
                    ->schema([
                        Forms\Components\Textarea::make('data')
                            ->label('Configuration JSON')
                            ->rows(20)
                            ->columnSpanFull()
                            ->placeholder('{}')
                            ->helperText(self::dataHelperText())
                            ->dehydrated(fn ($get) => !in_array($get('panel'), config('game_events.gacha_panels', [])))
                            ->rules([
                                fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                    if ($value === null || $value === '' || $value === '{}') {
                                        return;
                                    }
                                    json_decode((string) $value);
                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                        $fail('Configuration JSON is not valid JSON: ' . json_last_error_msg() . '.');
                                    }
                                },
                            ])
                            ->afterStateHydrated(function ($state, $set) {
                                // Model casts data as array; convert to pretty JSON for the textarea.
                                if (is_array($state)) {
                                    $set('data', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                } elseif ($state === null) {
                                    $set('data', '{}');
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                // Save back as array (model cast handles persistence).
                                $decoded = json_decode((string) $state, true);
                                return is_array($decoded) ? $decoded : [];
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'seasonal'  => 'success',
                        'permanent' => 'info',
                        'feature'   => 'warning',
                        'package'   => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('panel')
                    ->label('Panel')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'seasonal'  => 'Seasonal',
                        'permanent' => 'Permanent',
                        'feature'   => 'Feature',
                        'package'   => 'Package',
                    ]),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                \Filament\Actions\Action::make('toggleActive')
                    ->label(fn (GameEvent $record): string => $record->active ? 'Deactivate' : 'Activate')
                    ->icon(fn (GameEvent $record): string => $record->active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (GameEvent $record): string => $record->active ? 'warning' : 'success')
                    ->action(fn (GameEvent $record) => $record->update(['active' => !$record->active]))
                    ->tooltip(fn (GameEvent $record): string => $record->active ? 'Click to deactivate' : 'Click to activate'),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // JSON config templates shown as helper text in the admin form.
    // -------------------------------------------------------------------------

    private static function dataHelperText(): string
    {
        return <<<'HELP'
Templates by panel name:

── Monster Hunter (panel = "MonsterHunter") ──────────────────────────────────
{
  "boss_id":    "ene_2005",
  "energy_max": 100,
  "energy_cost": 20,
  "end":        "Mar 31, 2026",
  "rewards":    ["material_mh_badge:3", "gold_10000", "xp_percent_5"]
}

── Dragon Hunt (panel = "DragonHunt") ────────────────────────────────────────
{
  "material_token_cost":  10,
  "normal_mode_gold_cost": 250000,
  "easy_mode_token_cost":  100,
  "capture_range": { "0": [0,5], "1": [0,15], "2": [0,25] },
  "rewards_per_boss": {
    "enemy_dragon_1": ["material_db_1:3", "gold_50000"],
    "enemy_dragon_2": ["material_db_1:5", "gold_75000"]
  },
  "rewards_default": ["gold_10000"]
}

── Dragon Gacha (panel = "DragonGacha") ──────────────────────────────────────
{
  "pool_weights": [5, 25, 70],
  "draws": {
    "normal":         { "qty": 1, "coin_cost": 1, "token_cost": 25  },
    "advanced":       { "qty": 2, "coin_cost": 2, "token_cost": 50  },
    "advanced_bonus": { "qty": 6,                 "token_cost": 250 }
  },
  "pool": {
    "top": [
      "tokens_2000",
      "wpn_1121", "wpn_1122", "wpn_980", "wpn_986", "wpn_991", "wpn_992",
      "wpn_1014", "wpn_1018", "wpn_1034", "wpn_1035", "wpn_1036", "wpn_1044",
      "back_418", "back_422", "back_426", "back_435", "back_436",
      "back_458", "back_466", "back_476", "back_477", "back_478", "back_480",
      "pet_goldclowndragon", "pet_celebrationclowndragon", "pet_icebluedragon",
      "pet_lightningdrake", "pet_undeadchaindragon", "pet_darkthundertripledragon",
      "pet_dualcannontripledragon", "pet_minikirin", "pet_earthlavadragonturtle",
      "material_819", "material_820", "material_821", "material_822", "material_823",
      "material_205"
    ],
    "mid": [
      "hair_223_%s", "hair_225_%s", "hair_226_%s", "hair_229_%s", "hair_230_%s",
      "hair_231_%s", "hair_233_%s", "hair_248_%s", "hair_250_%s", "hair_251_%s",
      "hair_252_%s",
      "set_839_%s", "set_840_%s", "set_841_%s", "set_842_%s", "set_843_%s",
      "set_844_%s", "set_845_%s", "set_846_%s", "set_847_%s", "set_848_%s",
      "set_849_%s", "set_850_%s",
      "material_200", "material_201", "material_202", "material_203", "material_204",
      "material_1001",
      "essential_03", "essential_04", "essential_05",
      "item_52", "item_54",
      "tokens_"
    ],
    "common": [
      "material_773", "material_775", "material_776", "material_777", "material_778",
      "material_779", "material_780", "material_781", "material_782", "material_783",
      "material_784", "material_785", "material_786", "material_787", "material_788",
      "material_789", "material_790", "material_791", "material_792", "material_793",
      "material_794", "material_795", "material_796", "material_797", "material_798",
      "material_799", "material_800", "material_801", "material_802", "material_803",
      "material_804", "material_805", "material_806", "material_807", "material_808",
      "material_809",
      "item_49", "item_50", "item_51",
      "item_33", "item_34", "item_35", "item_36",
      "item_40", "item_39", "item_38", "item_37",
      "item_44", "item_43", "item_42", "item_41",
      "item_24", "item_32", "item_31", "item_30", "item_29", "item_28", "item_27",
      "item_26", "item_25", "item_23", "item_22", "item_21", "item_20", "item_19",
      "item_18", "item_17", "item_16", "item_15", "item_14", "item_13", "item_12",
      "item_11", "item_10", "item_09", "item_08", "item_07", "item_06", "item_05",
      "item_04", "item_03", "item_02",
      "gold_"
    ]
  }
}
Notes:
• pool_weights — [top%, mid%, common%]; must add up to 100. Default: [5, 25, 70].
• draws.normal / advanced — playable with Dragon Coins (coin_cost) or Tokens (token_cost).
• draws.advanced_bonus — tokens only (5 rolls + 1 bonus = 6 total); omit coin_cost.
• Items above mirror the current gamedata.json reward list — remove/add IDs as needed.
• Gender-variable IDs use %s (e.g. "hair_223_%s") — resolved to the player's gender at roll time.
• tokens_ / gold_ with no suffix are treated as wildcard reward types by the grant service.

── Justice Badge (panel = "JusticeBadge") ────────────────────────────────────
{
  "end": "Mar 31, 2026",
  "rewards": [
    { "id": "xp_percent_100", "requirement": 5  },
    { "id": "gold_50000",     "requirement": 10 },
    { "id": "tp_100",         "requirement": 15 },
    { "id": "ss_50",          "requirement": 20 }
  ]
}

── Confronting Death (panel = "ConfrontingDeathMenu") ────────────────────────
{
  "energy_max":        8,
  "energy_cost":       1,
  "refill_token_cost": 50,
  "boss_id":           "enemy_cd_boss",
  "rewards_win": ["material_cd_1:3", "gold_10000"],
  "milestones": [
    { "requirement": 5,   "reward": "item_%s_hair_1"  },
    { "requirement": 10,  "reward": "item_%s_set_1"   },
    { "requirement": 20,  "reward": "gold_50000"      },
    { "requirement": 30,  "reward": "tp_200"          },
    { "requirement": 50,  "reward": "item_%s_back_1"  },
    { "requirement": 70,  "reward": "gold_100000"     },
    { "requirement": 90,  "reward": "skill_%s_xxx"    },
    { "requirement": 120, "reward": "skill_%s_yyy"    }
  ],
  "skills": [
    { "id": "skill_%s_a", "price": [200, 150] },
    { "id": "skill_%s_b", "price": [300, 250] }
  ]
}

── Feast of Gratitude (panel = "FeastOfGratitudeMenu") ───────────────────────
{
  "energy_max":        10,
  "energy_cost":       1,
  "refill_token_cost": 50,
  "rewards_win": ["material_tg_1:3", "gold_10000"],
  "milestones": [
    { "requirement": 5,   "reward": "item_%s_hair_1"  },
    { "requirement": 10,  "reward": "item_%s_set_1"   },
    { "requirement": 20,  "reward": "gold_50000"      },
    { "requirement": 30,  "reward": "tp_200"          },
    { "requirement": 50,  "reward": "item_%s_back_1"  },
    { "requirement": 70,  "reward": "gold_100000"     },
    { "requirement": 90,  "reward": "skill_%s_xxx"    },
    { "requirement": 120, "reward": "skill_%s_yyy"    }
  ],
  "package": {
    "price":   [200, 150],
    "rewards": ["skill_%s_a", "item_bg_1", "gold_100000", "material_tg_1:10"]
  }
}

── Christmas / Yuki Onna (panel = "ChristmasMenu") ──────────────────────────
{
  "bosses": [
    {
      "id":          ["ene_2117"],
      "name":        "Yuki Onna Warrior",
      "description": "A foe born of blizzards.",
      "levels":      [0, 5],
      "xp":          "level * 2500 / 60",
      "gold":        "level * 2500 / 60",
      "rewards":     ["material_2226", "material_2228", "material_2231"],
      "background":  "mission_1065"
    }
  ],
  "minigame": ["material_2230", "material_2231"],
  "new_year":  ["hair_2364_%s", "set_2405_%s"],
  "milestone_battle": [
    { "id": "gold_100000",   "quantity": 1,  "requirement": 10  },
    { "id": "material_xxx",  "quantity": 10, "requirement": 50  },
    { "id": "hair_%s_xxx",   "quantity": 1,  "requirement": 100 },
    { "id": "essential_05",  "quantity": 5,  "requirement": 200 },
    { "id": "set_%s_xxx",    "quantity": 1,  "requirement": 300 },
    { "id": "tokens_150",    "quantity": 1,  "requirement": 400 },
    { "id": "back_xxx",      "quantity": 1,  "requirement": 600 },
    { "id": "wpn_xxx",       "quantity": 1,  "requirement": 750 }
  ],
  "rewards_preview": {
    "hair": [], "set": [], "back": [], "weapon": [], "skill": []
  }
}
Notes:
• Battle energy max: 10 hearts (hardcoded in SWF). Minigame energy max: 8 hearts.
• Both energy pools refill for 50 tokens (refillEnergy / refillMinigameEnergy).
• bosses[].id is an ARRAY — all listed enemies appear in the fight.
• minigame[] — items granted when Pet Frenzy minigame is won (all items always granted).
• new_year[] — one-time free gift handled by NewYear2026.claim (separate AMF call).
• refill_token_cost key is optional; defaults to 50.

── Halloween / Anniversary (panel = "HalloweenMenu") ────────────────────────
{
  "energy_max":        8,
  "energy_cost":       1,
  "refill_token_cost": 50,
  "bosses": [
    {
      "id":          ["enemy_hw_1"],
      "name":        "Boss Name",
      "description": "A spooky foe.",
      "levels":      [-5, 5],
      "gold":        "level*100",
      "rewards":     ["material_hw_1:3", "gold_10000"],
      "background":  "field_bg"
    }
  ],
  "milestone_battle": [
    { "id": "item_%s_hair_1", "requirement": 5,   "quantity": 1 },
    { "id": "item_%s_set_1",  "requirement": 10,  "quantity": 1 },
    { "id": "gold_50000",     "requirement": 20,  "quantity": 1 },
    { "id": "tp_200",         "requirement": 30,  "quantity": 1 },
    { "id": "item_%s_back_1", "requirement": 50,  "quantity": 1 },
    { "id": "gold_100000",    "requirement": 70,  "quantity": 1 },
    { "id": "skill_%s_xxx",   "requirement": 90,  "quantity": 1 },
    { "id": "skill_%s_yyy",   "requirement": 120, "quantity": 1 }
  ],
  "rewards_preview": {
    "hair": [], "set": [], "back": [], "weapon": [], "skill": []
  }
}
Notes:
• bosses[].id is an ARRAY of enemy IDs — all listed enemies appear in the fight.
• Add multiple objects to "bosses" for player-selectable boss encounters.
• milestone_battle[].id supports %s (gender-resolved) and standard reward strings.
• rewards_preview arrays are shown in the client reward preview panel (cosmetic only).

── Reward string format ──────────────────────────────────────────────────────
  "gold_50000"         → 50 000 gold
  "xp_percent_100"     → 100% of current level XP
  "tp_200"             → 200 Training Points
  "ss_50"              → 50 Scrolls
  "tokens_100"         → 100 tokens
  "material_xxx:5"     → 5× item material_xxx
  "item_xxx"           → 1× item_xxx
  "skill_%s_xxx"       → skill (gender-resolved: 0/1)
  "%s" in any id is replaced with character gender (0 or 1).
HELP;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGameEvents::route('/'),
            'create' => Pages\CreateGameEvent::route('/create'),
            'edit'   => Pages\EditGameEvent::route('/{record}/edit'),
        ];
    }
}