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

class SkillCreator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?string $navigationLabel = 'Skill Creator';
    protected static string|\UnitEnum|null $navigationGroup = 'Game Data';
    protected static ?string $title = 'Skill Creator';
    protected string $view = 'filament.pages.skill-creator';
    protected static ?int $navigationSort = 10;

    // ── Form State ────────────────────────────────────────────────────────────

    public bool   $isEditing        = false;
    public string $loadSkillSelect  = '';

    public string $skill_id = '';
    public string $skill_name = '';
    public string $skill_description = '';
    public string $skill_type = '1';
    public int|string $skill_level = 1;
    public int|string $skill_damage = 0;
    public int|string $skill_cp_cost = 0;
    public int|string $skill_cooldown = 0;
    public string $skill_target = 'Single';
    public int|string $skill_hit_chance = 0;
    public string $skill_attack_hit_position = 'range_1';
    public string $skill_anims_hit = '';
    public string $skill_swf = '';
    public bool $skill_multi_hit = false;
    public bool $skill_buyable = true;
    public bool $skill_premium = false;
    public int|string $skill_price_gold = 0;
    public int|string $skill_price_tokens = 0;
    public bool $skill_na = false;
    public array $skill_icon = [];
    public array $skill_effects = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->suggestNextId();
    }

    private function suggestNextId(): void
    {
        $skills = $this->loadSkills();
        $maxNum = 0;
        foreach ($skills as $s) {
            if (preg_match('/^skill_(\d+)$/', $s['id'] ?? '', $m)) {
                $maxNum = max($maxNum, (int) $m[1]);
            }
        }
        $this->skill_id = 'skill_' . ($maxNum + 1);
    }

    // ── Load existing skill into the form ─────────────────────────────────────

    public function loadSkill(): void
    {
        $id = trim($this->loadSkillSelect);
        if (!$id) return;

        $skills = $this->loadSkills();
        $skill  = null;
        foreach ($skills as $s) {
            if (($s['id'] ?? '') === $id) { $skill = $s; break; }
        }

        if (!$skill) {
            // Not in skills.json — try to load basic fields from the DB so the
            // user can fill in the remaining fields and click Update Skill to
            // write it into skills.json.
            $dbSkill = Skill::where('skill_id', $id)->first();
            if (!$dbSkill) {
                Notification::make()->title("Skill \"{$id}\" not found in skills.json or the database.")->danger()->send();
                return;
            }
            $this->skill_id          = $dbSkill->skill_id;
            $this->skill_name        = $dbSkill->name        ?? '';
            $this->skill_description = '';
            // element column stores 1-5 for elemental types; non-elemental
            // types (6,7,9,10,11) are all stored as 0 — cannot be recovered
            // from DB alone, so leave the dropdown at its default and let the
            // user set it manually before clicking Update Skill.
            $dbElement = (int) ($dbSkill->element ?? 0);
            $this->skill_type = $dbElement >= 1 ? (string) $dbElement : '1';
            $this->skill_level       = (int) ($dbSkill->level    ?? 1);
            $this->skill_damage      = 0;
            $this->skill_cp_cost     = 0;
            $this->skill_cooldown    = 0;
            $this->skill_target      = 'Single';
            $this->skill_hit_chance  = 0;
            $this->skill_attack_hit_position = 'range_1';
            $this->skill_multi_hit   = false;
            $this->skill_buyable     = false;
            $this->skill_premium     = (bool) ($dbSkill->premium ?? false);
            $this->skill_price_gold  = (int) ($dbSkill->price_gold   ?? 0);
            $this->skill_price_tokens= (int) ($dbSkill->price_tokens ?? 0);
            $this->skill_na          = false;
            $this->skill_anims_hit   = '';
            $this->skill_swf         = $dbSkill->swf ?? '';
            $this->skill_icon        = [];
            $this->skill_effects     = [];
            $this->isEditing         = true;
            $typeWarning = $dbElement === 0
                ? ' ⚠️ Type/Element could not be recovered (non-elemental types are stored as 0 in the DB) — check and set the correct Type before saving.'
                : '';
            Notification::make()
                ->title("Skill \"{$id}\" loaded from DB only — it is missing from skills.json!")
                ->body('Fill in the missing fields (damage, CP cost, cooldown, description, etc.) then click Update Skill to write it to skills.json.' . $typeWarning)
                ->warning()
                ->send();
            return;
        }

        // Basic fields
        $this->skill_id          = $skill['id'];
        $this->skill_name        = $skill['name']        ?? '';
        $this->skill_description = $skill['description'] ?? '';
        $this->skill_type        = (string) ($skill['type'] ?? '1');
        $this->skill_level       = (int) ($skill['level']    ?? 1);
        $this->skill_damage      = (int) ($skill['damage']   ?? 0);
        $this->skill_cp_cost     = (int) ($skill['cp_cost']  ?? 0);
        $this->skill_cooldown    = (int) ($skill['cooldown'] ?? 0);
        $this->skill_target      = $skill['target']      ?? 'Single';
        $this->skill_hit_chance  = (int) ($skill['hit_chance'] ?? 0);
        $this->skill_attack_hit_position = $skill['attack_hit_position'] ?? 'range_1';
        $this->skill_multi_hit   = (bool) ($skill['multi_hit'] ?? false);
        $this->skill_buyable     = (bool) ($skill['buyable']   ?? true);
        $this->skill_premium     = (bool) ($skill['premium']   ?? false);
        $this->skill_price_gold  = (int) ($skill['price_gold']   ?? 0);
        $this->skill_price_tokens= (int) ($skill['price_tokens'] ?? 0);
        $this->skill_na          = !empty($skill['na']);

        // Animations: ['hit' => [33, 59]] → "33,59"
        $animIds = $skill['anims']['hit'] ?? [];
        $this->skill_anims_hit = implode(',', $animIds);

        // SWF alias from DB (not stored in skills.json)
        $dbSkill        = Skill::where('skill_id', $id)->first();
        $this->skill_swf = $dbSkill?->swf ?? '';
        $this->skill_icon = [];   // FileUpload can't pre-populate from stored path

        // Effects from skill-effect.json
        $effects     = $this->loadSkillEffects();
        $effectEntry = null;
        foreach ($effects as $e) {
            if (($e['skill_id'] ?? '') === $id) { $effectEntry = $e; break; }
        }
        $this->skill_effects = $effectEntry['skill_effect'] ?? [];

        $this->isEditing = true;

        Notification::make()
            ->title("Loaded \"{$this->skill_name}\" ({$id}) — make your changes and click Update Skill.")
            ->info()
            ->send();
    }

    // ── Reset back to "create new" mode ───────────────────────────────────────

    public function resetToNew(): void
    {
        $this->isEditing       = false;
        $this->loadSkillSelect = '';
        $this->skill_name      = '';
        $this->skill_description = '';
        $this->skill_damage    = 0;
        $this->skill_cp_cost   = 0;
        $this->skill_cooldown  = 0;
        $this->skill_anims_hit = '';
        $this->skill_swf       = '';
        $this->skill_effects   = [];
        $this->skill_level     = 1;
        $this->skill_target    = 'Single';
        $this->skill_attack_hit_position = 'range_1';
        $this->skill_type      = '1';
        $this->skill_multi_hit = false;
        $this->skill_buyable   = true;
        $this->skill_premium   = false;
        $this->skill_price_gold   = 0;
        $this->skill_price_tokens = 0;
        $this->skill_na        = false;
        $this->skill_icon      = [];
        $this->suggestNextId();
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            // ── Load existing skill ───────────────────────────────────────────
            Section::make('Edit Existing Skill')
                ->description('Select a skill from the list to load all its current values into the form below. After editing click "Update Skill". To create a brand-new skill instead, leave this blank.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Select::make('loadSkillSelect')
                            ->label('Load Skill to Edit')
                            ->placeholder('Search by name or ID…')
                            ->options(function () {
                                $skills = $this->loadSkills();
                                $opts   = [];
                                $jsonIds = [];
                                foreach ($skills as $s) {
                                    $opts[$s['id']] = $s['id'] . ' — ' . ($s['name'] ?? '');
                                    $jsonIds[] = $s['id'];
                                }
                                // Include DB-only skills (missing from skills.json)
                                $dbSkills = Skill::whereNotIn('skill_id', $jsonIds)->get();
                                foreach ($dbSkills as $db) {
                                    $opts[$db->skill_id] = $db->skill_id . ' — ' . $db->name . ' [DB only — not in skills.json]';
                                }
                                return $opts;
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->loadSkill()),
                    ]),
                ])
                ->collapsible()
                ->collapsed(fn () => !$this->isEditing),

            // ── Basic Information ─────────────────────────────────────────────
            Section::make('Basic Information')
                ->description('Core skill identity. The ID is how the server and client reference this skill everywhere — it must be unique and cannot be changed after creation without directly editing skills.json.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('skill_id')
                            ->label('Skill ID')
                            ->helperText('Auto-suggested as the next available number. Format must be skill_XXX (e.g. skill_1159). Must not already exist in skills.json. Once the skill is created this ID is permanent.')
                            ->required()
                            ->disabled(fn () => $this->isEditing)
                            ->extraInputAttributes(['class' => 'font-mono']),

                        Forms\Components\TextInput::make('skill_name')
                            ->label('Name')
                            ->helperText('Display name shown in the skill list, academy, shop, and battle UI tooltip header.')
                            ->required()
                            ->placeholder('e.g. Flame Dragon Strike'),
                    ]),

                    Forms\Components\Textarea::make('skill_description')
                        ->label('Description')
                        ->helperText('Full text shown in the skill tooltip popup. Be specific — include what effects are applied, their duration, and any conditions. Players read this to decide whether to buy the skill.')
                        ->rows(3)
                        ->placeholder('e.g. Summons a flaming dragon that strikes the target, burning them for 3 turns and reducing their accuracy by 20%.'),

                    Forms\Components\FileUpload::make('skill_icon')
                        ->label('Skill Icon')
                        ->helperText('Upload a PNG or JPG icon for this skill. Displayed in the player\'s skill inventory and the shop. Stored in storage/app/public/skills/. If left blank the skill appears without an icon in the inventory panel.')
                        ->image()
                        ->directory('skills')
                        ->visibility('public')
                        ->imagePreviewHeight('80')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif'])
                        ->nullable(),
                ]),

            Section::make('Combat Stats')
                ->description('Controls how the skill behaves in battle. Damage and CP cost set the power level; cooldown controls frequency of use.')
                ->schema([
                    Grid::make(3)->schema([
                        Forms\Components\Select::make('skill_type')
                            ->label('Type / Element')
                            ->helperText('Controls which inventory tab the skill appears under. Types 1–5 appear under the matching elemental tab (Wind/Fire/etc). Type 6 = Taijutsu tab. Type 7 = Genjutsu tab. Types 9–11 do NOT appear in the skill inventory — only use them if the skill is handled outside the normal inventory (e.g. PvP-only or server-side special skills).')
                            ->options([
                                '1'  => '1 — Wind Ninjutsu → Wind inventory tab',
                                '2'  => '2 — Fire Ninjutsu → Fire inventory tab',
                                '3'  => '3 — Lightning Ninjutsu → Lightning inventory tab',
                                '4'  => '4 — Earth Ninjutsu → Earth inventory tab',
                                '5'  => '5 — Water Ninjutsu → Water inventory tab',
                                '6'  => '6 — Taijutsu → Taijutsu inventory tab',
                                '7'  => '7 — Genjutsu / Buff-Debuff → Genjutsu inventory tab',
                                '9'  => '9 — PvP Special (⚠ no inventory tab — invisible to players)',
                                '10' => '10 — Medical / Recovery (⚠ no inventory tab — invisible to players)',
                                '11' => '11 — Senjutsu / Sage Mode (⚠ no inventory tab — invisible to players)',
                            ])
                            ->required()
                            ->default('1'),

                        Forms\Components\TextInput::make('skill_level')
                            ->label('Level Required')
                            ->helperText('Minimum character level to equip and activate this skill (1–200). Players below this level see the skill as locked in the UI and cannot use it in battle.')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(200),

                        Forms\Components\Select::make('skill_target')
                            ->label('Target')
                            ->helperText('Who the skill acts on when activated in battle. "All" hits every enemy simultaneously and can be very powerful — balance damage accordingly.')
                            ->options([
                                'Single' => 'Single — hits one chosen enemy',
                                'Self'   => 'Self — applies to the caster only (buffs, heals, self-debuffs)',
                                'All'    => 'All — hits every enemy at the same time (AoE)',
                            ])
                            ->required()
                            ->default('Single'),
                    ]),

                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('skill_damage')
                            ->label('Damage')
                            ->helperText('Base damage value. The server multiplies this by the character\'s attack stat using the combat formula. Set to 0 for buff/debuff skills that deal no direct damage. Typical damage range: 20–300.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('skill_cp_cost')
                            ->label('CP Cost')
                            ->helperText('Chakra Points spent each time the skill activates. If the player\'s current CP is lower than this value the skill button is locked automatically. Typical range: 20–120 CP.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('skill_cooldown')
                            ->label('Cooldown (turns)')
                            ->helperText('How many turns the skill is unavailable after use. 0 = usable every single turn. Balanced skills: 3–5. Powerful skills: 7–10. The counter starts after the skill resolves.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Grid::make(3)->schema([
                        Forms\Components\TextInput::make('skill_hit_chance')
                            ->label('Hit Chance Modifier')
                            ->helperText('Percentage-point adjustment to base accuracy. 0 = standard hit formula with no modification. Positive values make the skill more accurate (e.g. +30 = much harder to dodge). Negative values create intentionally inaccurate skills.')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Select::make('skill_attack_hit_position')
                            ->label('Attack Hit Position')
                            ->helperText('Controls how the caster moves in the battle arena and which animation style plays. Melee positions dash the caster to the target; range positions fire a projectile from a distance; startpos keeps the caster in place (for AoE, self-casts, and field effects).')
                            ->options([
                                'meele_1' => 'meele_1 — Melee (short advance, step 1)',
                                'meele_2' => 'meele_2 — Melee (step 2)',
                                'meele_3' => 'meele_3 — Melee (step 3)',
                                'meele_4' => 'meele_4 — Melee (full dash to target, step 4)',
                                'range_1' => 'range_1 — Ranged projectile (near distance)',
                                'range_2' => 'range_2 — Ranged projectile (mid distance)',
                                'range_3' => 'range_3 — Ranged projectile (far distance)',
                                'startpos' => 'startpos — Stay in place (AoE / self-cast / field effects)',
                            ])
                            ->required()
                            ->default('range_1'),

                        Forms\Components\Toggle::make('skill_multi_hit')
                            ->label('Multi-Hit')
                            ->helperText('When ON the server allows this skill to strike the target multiple times per use, with a damage reduction multiplier applied to each successive hit. Used for rapid-strike or combo-style skills.')
                            ->default(false),
                    ]),
                ]),

            Section::make('Animation')
                ->description('Animation SWF files played when the skill fires. You can freely reuse any existing animation ID — no new SWF file is needed for new skills.')
                ->schema([
                    Forms\Components\TextInput::make('skill_anims_hit')
                        ->label('Hit Animation Frame (comma-separated)')
                        ->helperText('The 0-based frame index inside the skill SWF at which the hit fires. This is NOT a file ID — it is the frame number within the loaded SWF. For example, skill_06 (Dual Twister) fires its hit at frame index 59, so you would enter "59" here. If you set a Base SWF Skill ID below and leave this blank, the base skill\'s hit frame(s) will be copied automatically on save. Enter multiple frames separated by commas for multi-hit.')
                        ->placeholder('e.g. 59')
                        ->extraInputAttributes(['class' => 'font-mono']),

                    Forms\Components\TextInput::make('skill_swf')
                        ->label('Base SWF Skill ID (inventory display + battle animation)')
                        ->helperText('The skill_id of an existing skill whose SWF file this skill uses for both the inventory panel display and the battle animation. For example, entering "skill_06" makes this skill play the Dual Twister animation in battle (hit frame 59) and show skill_06\'s visuals in the inventory. Leave blank to use the global fallback (skill_01). The skill\'s own stats, name, and effects are always used — only the visual is borrowed. If you set this and leave Hit Animation Frame blank, the base skill\'s frame is auto-copied on save.')
                        ->placeholder('e.g. skill_06')
                        ->extraInputAttributes(['class' => 'font-mono']),
                ]),

            Section::make('Shop & Availability')
                ->description('Controls whether this skill appears in the skill shop/academy and what it costs to purchase.')
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\Toggle::make('skill_buyable')
                            ->label('Buyable')
                            ->helperText('When ON this skill appears in the skill shop/academy and players can purchase it normally. When OFF it is invisible to players and can only be granted via admin commands or special events.')
                            ->default(true),

                        Forms\Components\Toggle::make('skill_premium')
                            ->label('Premium Only')
                            ->helperText('When ON the skill requires premium Tokens to purchase. You must also set price_tokens to a non-zero value. If both buyable and premium are ON with a token price set, the skill costs tokens. If premium is ON but price_tokens = 0 it is a free-premium skill.')
                            ->default(false),
                    ]),

                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('skill_price_gold')
                            ->label('Price (Gold)')
                            ->helperText('In-game gold cost. Only active when buyable = ON. Set to 0 if the skill costs tokens only or is free. Players spend this from their character\'s gold wallet.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Forms\Components\TextInput::make('skill_price_tokens')
                            ->label('Price (Tokens)')
                            ->helperText('Premium token cost. Requires premium = ON to be enforced. Set to 0 for gold-only skills. Tokens are the account-level premium currency.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),

                    Forms\Components\Toggle::make('skill_na')
                        ->label('Mark as N/A (Not Available)')
                        ->helperText('Flags the skill as not available in certain client display contexts (e.g. hidden from specific skill list views). Leave OFF unless you intentionally want to hide this skill from the standard UI while keeping it in the data file.')
                        ->default(false),
                ]),

            Section::make('Skill Effects')
                ->description('Secondary effects that trigger when this skill lands. Each row is one effect. All effects in the list trigger simultaneously unless limited by their Chance %. A skill with an empty list is a pure damage skill with no extra effects.')
                ->schema([
                    Forms\Components\Repeater::make('skill_effects')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\Select::make('target')
                                    ->label('Target')
                                    ->helperText('Who receives this specific effect — the opponent or the caster.')
                                    ->options([
                                        'enemy' => 'enemy — effect lands on the opponent',
                                        'self'  => 'self — effect lands on the caster (use for self-buffs)',
                                    ])
                                    ->required()
                                    ->default('enemy'),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->helperText('Effect classification used by the client UI and game logic. "Debuff" effects are removed by the Purify skill. "Buff" effects can be stripped by Disperse-type skills. Choose whichever matches what the effect actually does.')
                                    ->options([
                                        'Debuff' => 'Debuff — harmful effect (removed by Purify)',
                                        'Buff'   => 'Buff — beneficial effect (removed by Disperse)',
                                    ])
                                    ->required()
                                    ->default('Debuff'),
                            ]),

                            Grid::make(2)->schema([
                                Forms\Components\Select::make('effect')
                                    ->label('Effect')
                                    ->helperText('The actual game mechanic triggered. Each value maps to a specific server handler function. Use the search box to filter. Misspelled or unknown effect values are silently ignored by the client — only the exact strings listed here are valid.')
                                    ->options(self::skillEffectOptions())
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('effect_name')
                                    ->label('Display Name')
                                    ->helperText('Short label shown above the character portrait in the battle UI when this effect is active (e.g. "Stun", "Poison", "Power Up"). Keep it short — 1 to 3 words. Does not need to exactly match the effect key.')
                                    ->required()
                                    ->placeholder('e.g. Burning'),
                            ]),

                            Grid::make(4)->schema([
                                Forms\Components\TextInput::make('duration')
                                    ->label('Duration (turns)')
                                    ->helperText('How many turns the effect stays active. 0 = instant one-shot effect that resolves immediately (heals, instant damage, drains). 1+ = persists on the target and ticks each turn for that many turns.')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),

                                Forms\Components\Select::make('calc_type')
                                    ->label('Calc Type')
                                    ->helperText('How the Amount field is interpreted by the server. None = no quantity needed (use for stun, sleep, freeze). number = fixed flat value (e.g. 50 HP). percent = percentage of the target\'s max HP or CP. added_percent = stacks additively with an existing percentage modifier.')
                                    ->options([
                                        ''              => 'None — no amount needed (stun, sleep, freeze…)',
                                        'number'        => 'number — fixed flat value (e.g. 50 HP healed)',
                                        'percent'       => 'percent — % of max HP or CP (e.g. 20 = 20%)',
                                        'added_percent' => 'added_percent — adds to an existing % multiplier',
                                    ])
                                    ->default(''),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->helperText('Magnitude of the effect. Ignored when Calc Type is None. For "number": e.g. 50 = 50 HP. For "percent": e.g. 20 = 20% of max. For "added_percent": e.g. 10 adds 10% to the existing multiplier. Use 0 when no amount is needed.')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\TextInput::make('chance')
                                    ->label('Chance (%)')
                                    ->helperText('Probability this specific effect triggers when the skill resolves, from 0 to 100. 100 = always applies. 50 = 50/50. The skill\'s direct damage always applies — this chance only controls whether this secondary effect fires.')
                                    ->numeric()
                                    ->default(100)
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),
                        ])
                        ->addActionLabel('+ Add Effect')
                        ->reorderableWithDragAndDrop()
                        ->itemLabel(fn (array $state): ?string =>
                            !empty($state['effect'])
                                ? ($state['type'] ?? 'Effect') . ': ' . $state['effect'] . ' → ' . ($state['target'] ?? '?') . ' (' . ($state['chance'] ?? 100) . '%' . (!empty($state['duration']) ? ', ' . $state['duration'] . 't' : '') . ')'
                                : 'New Effect'
                        )
                        ->defaultItems(0)
                        ->collapsible(),
                ]),
        ]);
    }

    // ── Header Actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(fn () => $this->isEditing ? 'Update Skill' : 'Create Skill')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->action('save'),

            Action::make('resetToNew')
                ->label('New Skill')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('gray')
                ->visible(fn () => $this->isEditing)
                ->action('resetToNew'),
        ];
    }

    // ── Save (create or update) ────────────────────────────────────────────────

    public function save(): void
    {
        if ($this->isEditing) {
            $this->update();
        } else {
            $this->create();
        }
    }

    // ── Create ────────────────────────────────────────────────────────────────

    private function create(): void
    {
        $skillId = trim($this->skill_id);

        if (!preg_match('/^skill_\d+$/', $skillId)) {
            Notification::make()->title('Invalid Skill ID — must be in the format skill_XXX (e.g. skill_1159).')->danger()->send();
            return;
        }

        $skills = $this->loadSkills();
        foreach ($skills as $s) {
            if (($s['id'] ?? '') === $skillId) {
                Notification::make()->title("Skill ID \"{$skillId}\" already exists in skills.json. Choose a different ID.")->danger()->send();
                return;
            }
        }

        $newSkill = $this->buildSkillEntry($skillId);
        $skills[] = $newSkill;

        $skillsPath = base_path('public/game_data/skills.json');
        if (file_put_contents($skillsPath, json_encode($skills, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            Notification::make()->title('Failed to write skills.json — check file permissions.')->danger()->send();
            return;
        }
        $this->writeBin($skillsPath, 'skills');

        $elementMap = ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5];
        $dbData = [
            'name'         => trim($this->skill_name),
            'level'        => (int) $this->skill_level,
            'element'      => $elementMap[$this->skill_type] ?? 0,
            'premium'      => (bool) $this->skill_premium,
            'price_gold'   => (int) $this->skill_price_gold,
            'price_tokens' => (int) $this->skill_price_tokens,
            'icon'         => !empty($this->skill_icon) ? (is_array($this->skill_icon) ? reset($this->skill_icon) : $this->skill_icon) : null,
            'swf'          => trim($this->skill_swf) ?: null,
        ];
        Skill::updateOrCreate(['skill_id' => $skillId], $dbData);

        $this->saveEffects($skillId);
        $this->generateSkillSwf($skillId);

        Notification::make()
            ->title("Skill \"{$this->skill_name}\" ({$skillId}) created — saved to DB, skills.json, and skills.bin!")
            ->body('Players must reload the game (browser refresh) for the new skill to appear in their inventory.')
            ->success()
            ->send();

        $nextNum = (int) substr($skillId, 6) + 1;
        $this->resetToNew();
        $this->skill_id = 'skill_' . $nextNum;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    private function update(): void
    {
        $skillId = trim($this->skill_id);

        $skills  = $this->loadSkills();
        $found   = false;
        foreach ($skills as &$s) {
            if (($s['id'] ?? '') === $skillId) {
                $s     = $this->buildSkillEntry($skillId);
                $found = true;
                break;
            }
        }
        unset($s);

        if (!$found) {
            // Skill exists in DB but is missing from skills.json (e.g. created manually).
            // Append it so the Flash client can find it.
            $skills[] = $this->buildSkillEntry($skillId);
        }

        $skillsPath = base_path('public/game_data/skills.json');
        if (file_put_contents($skillsPath, json_encode($skills, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            Notification::make()->title('Failed to write skills.json — check file permissions.')->danger()->send();
            return;
        }
        $this->writeBin($skillsPath, 'skills');

        // Update DB record
        $elementMap = ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5];
        $dbData = [
            'name'         => trim($this->skill_name),
            'level'        => (int) $this->skill_level,
            'element'      => $elementMap[$this->skill_type] ?? 0,
            'premium'      => (bool) $this->skill_premium,
            'price_gold'   => (int) $this->skill_price_gold,
            'price_tokens' => (int) $this->skill_price_tokens,
            'swf'          => trim($this->skill_swf) ?: null,
        ];
        if (!empty($this->skill_icon)) {
            $dbData['icon'] = is_array($this->skill_icon) ? reset($this->skill_icon) : $this->skill_icon;
        }
        Skill::where('skill_id', $skillId)->update($dbData);

        // Update skill-effect.json — replace existing entry or append
        $effectsPath = base_path('public/game_data/skill-effect.json');
        $effects     = $this->loadSkillEffects();
        $effectFound = false;
        foreach ($effects as &$e) {
            if (($e['skill_id'] ?? '') === $skillId) {
                if (!empty($this->skill_effects)) {
                    $e = ['skill_id' => $skillId, 'skill_effect' => $this->buildEffectList()];
                } else {
                    // Remove the entry entirely if effects were cleared
                    $e = null;
                }
                $effectFound = true;
                break;
            }
        }
        unset($e);
        $effects = array_values(array_filter($effects));

        if (!$effectFound && !empty($this->skill_effects)) {
            $effects[] = ['skill_id' => $skillId, 'skill_effect' => $this->buildEffectList()];
        }

        if (file_put_contents($effectsPath, json_encode($effects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
            $this->writeBin($effectsPath, 'skill-effect');
        }

        $this->generateSkillSwf($skillId);

        Notification::make()
            ->title("Skill \"{$this->skill_name}\" ({$skillId}) updated — skills.json, skills.bin, skill-effect.json, and DB all saved!")
            ->body('Players must reload the game (browser refresh) for changes to appear in their inventory.')
            ->success()
            ->send();
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private function buildSkillEntry(string $skillId): array
    {
        $animIds = array_values(array_filter(
            array_map('intval', array_filter(array_map('trim', explode(',', $this->skill_anims_hit))))
        ));

        // If no hit frames specified, inherit from the base SWF skill's anims.hit
        if (empty($animIds) && !empty(trim($this->skill_swf))) {
            $baseSkillId = trim($this->skill_swf);
            $skills      = $this->loadSkills();
            foreach ($skills as $s) {
                if (($s['id'] ?? '') === $baseSkillId) {
                    $animIds = $s['anims']['hit'] ?? [];
                    break;
                }
            }
        }

        $entry = [
            'id'                  => $skillId,
            'name'                => trim($this->skill_name),
            'description'         => trim($this->skill_description),
            'type'                => $this->skill_type,
            'level'               => (int) $this->skill_level,
            'damage'              => (int) $this->skill_damage,
            'cp_cost'             => (int) $this->skill_cp_cost,
            'cooldown'            => (int) $this->skill_cooldown,
            'target'              => $this->skill_target,
            'hit_chance'          => (int) $this->skill_hit_chance,
            'buyable'             => (bool) $this->skill_buyable,
            'premium'             => (bool) $this->skill_premium,
            'price_gold'          => (int) $this->skill_price_gold,
            'price_tokens'        => (int) $this->skill_price_tokens,
            'attack_hit_position' => $this->skill_attack_hit_position,
            'anims'               => empty($animIds) ? (object) [] : ['hit' => $animIds],
            'multi_hit'           => (bool) $this->skill_multi_hit,
        ];

        if ($this->skill_na) {
            $entry['na'] = true;
        }

        return $entry;
    }

    private function buildEffectList(): array
    {
        return array_values(array_map(fn ($e) => [
            'target'      => $e['target']      ?? 'enemy',
            'type'        => $e['type']        ?? 'Debuff',
            'effect'      => $e['effect']      ?? '',
            'effect_name' => $e['effect_name'] ?? '',
            'duration'    => (int) ($e['duration']  ?? 0),
            'calc_type'   => $e['calc_type']   ?? '',
            'amount'      => (int) ($e['amount']    ?? 0),
            'chance'      => (int) ($e['chance']    ?? 100),
        ], $this->skill_effects));
    }

    private function saveEffects(string $skillId): void
    {
        if (empty($this->skill_effects)) return;

        $effectsPath = base_path('public/game_data/skill-effect.json');
        $effects     = $this->loadSkillEffects();
        $effects[]   = ['skill_id' => $skillId, 'skill_effect' => $this->buildEffectList()];

        if (file_put_contents($effectsPath, json_encode($effects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
            $this->writeBin($effectsPath, 'skill-effect');
        } else {
            Notification::make()->title('Skill saved but failed to write skill-effect.json.')->warning()->send();
        }
    }

    // ── File Helpers ──────────────────────────────────────────────────────────

    private function loadSkills(): array
    {
        $path = base_path('public/game_data/skills.json');
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function loadSkillEffects(): array
    {
        $path = base_path('public/game_data/skill-effect.json');
        if (!file_exists($path)) return [];
        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function writeBin(string $jsonPath, string $binName): bool
    {
        $json = @file_get_contents($jsonPath);
        if ($json === false) return false;
        $compressed = gzcompress($json, 6);
        if ($compressed === false) return false;
        return (bool) file_put_contents(base_path('public/game_data/' . $binName . '.bin'), $compressed);
    }

    /**
     * Generate a patched SWF for a custom skill so that the Flash client's
     * content[skillId].gotoAndStop(1) call does not crash with TypeError #1010.
     *
     * The client loads skills/skill_XXXX.swf and accesses content["skill_XXXX"].
     * When the server falls back to a different SWF (e.g. skill_01.swf), that SWF
     * has a MovieClip named "skill_01" — not "skill_XXXX" — so content["skill_XXXX"]
     * is null and throws TypeError #1010 in UI_Skillset.completeIcon().
     *
     * This method clones the base SWF, renames the internal clip, and saves it as
     * the custom skill's own SWF so the client's lookup always finds a valid clip.
     */
    private function generateSkillSwf(string $skillId): void
    {
        $swfDir = env('SKILL_SWF_PATH', realpath(base_path() . '/../../../Client/skills'));
        if (!$swfDir || !is_dir($swfDir)) return;

        $outputPath = $swfDir . DIRECTORY_SEPARATOR . $skillId . '.swf';
        if (file_exists($outputPath)) return; // already created — nothing to do

        // Determine which SWF to clone: use the DB alias if set, else skill_01.swf
        $alias       = trim($this->skill_swf) ?: null;
        $baseSwfName = $alias ? ($alias . '.swf') : 'skill_01.swf';
        $basePath    = $swfDir . DIRECTORY_SEPARATOR . $baseSwfName;
        if (!file_exists($basePath)) return;

        $baseSkillId = pathinfo($basePath, PATHINFO_FILENAME); // e.g. "skill_06"
        if ($baseSkillId === $skillId) return; // nothing to rename

        $patchScript = base_path('scripts/patch_skill_swf.py');
        if (!file_exists($patchScript)) return;

        $cmd    = sprintf(
            'python3 %s %s %s %s %s 2>&1',
            escapeshellarg($patchScript),
            escapeshellarg($basePath),
            escapeshellarg($outputPath),
            escapeshellarg($baseSkillId),
            escapeshellarg($skillId)
        );
        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        if ($code !== 0) {
            \Illuminate\Support\Facades\Log::warning(
                "generateSkillSwf failed for {$skillId}: " . implode("\n", $output)
            );
        }
    }

    // ── Effect Option Lists ───────────────────────────────────────────────────

    private static function skillEffectOptions(): array
    {
        return [
            'Crowd Control' => [
                'stun'           => 'stun — target cannot act for N turns',
                'sleep'          => 'sleep — target cannot act; wakes when taking damage',
                'freeze'         => 'freeze — immobilizes target completely',
                'frozen'         => 'frozen — alternate freeze variant (same server handler)',
                'petrify'        => 'petrify — turns target to stone, fully disabling all actions',
                'restriction'    => 'restriction — limits what actions the target can take each turn',
                'charge_disable' => 'charge_disable — prevents the target from using charge-type actions',
                'lock'           => 'lock — prevents the target from switching or selecting skills',
                'confinement'    => 'confinement — confines target to a restricted area',
                'prison'         => 'prison — completely traps the target with no actions available',
                'kekkai'         => 'kekkai — barrier that both traps and damages the target',
                'domain_expansion' => 'domain_expansion — creates a battle domain zone with special combat rules',
            ],

            'Damage Over Time' => [
                'bleeding'        => 'bleeding — HP loss per turn (physical)',
                'burn'            => 'burn — fire damage per turn',
                'burning'         => 'burning — stronger fire damage per turn',
                'burningX'        => 'burningX — highest tier burning (most fire DoT)',
                'blaze'           => 'blaze — intense blaze damage per turn',
                'poison'          => 'poison — poison damage per turn',
                'internal_injury' => 'internal_injury — internal chakra damage per turn',
            ],

            'Stat Debuffs' => [
                'weaken'         => 'weaken — reduces target\'s outgoing attack damage',
                'blind'          => 'blind — reduces target\'s accuracy / hit chance',
                'numb'           => 'numb — reduces speed or action efficiency',
                'slow'           => 'slow — reduces target\'s action speed',
                'slow_attacker'  => 'slow_attacker — slows whoever is attacking the player',
                'chaos'          => 'chaos — applies unpredictable random debuff effects each turn',
                'fear'           => 'fear — reduces combat effectiveness through fear',
                'dark_curse'     => 'dark_curse — stacking dark debuff that worsens over multiple applications',
                'demonic_curse'  => 'demonic_curse — powerful demonic curse debuff',
                'disorient'      => 'disorient — disrupts target\'s ability to act effectively',
                'vulnerable'     => 'vulnerable — target receives more damage from all sources',
                'weak_body'      => 'weak_body — reduces all of target\'s defensive stats',
                'darkness'       => 'darkness — darkness field that reduces visibility and accuracy',
                'muddy'          => 'muddy — earth/mud effect hindering movement and speed',
                'suffocate'      => 'suffocate — reduces max HP each turn it remains active',
                'overload'       => 'overload — exceeds power limits, causing chaotic backlash damage',
                'overwhelm'      => 'overwhelm — breaks through defensive barriers and protections',
                'meridian_injury'=> 'meridian_injury — damages the target\'s chakra pathways',
                'meridian_seal'  => 'meridian_seal — seals chakra pathways, blocking skill activation',
            ],

            'Healing & Recovery' => [
                'heal'                => 'heal — instant HP restore (calc_type: number = flat HP, percent = % of max HP)',
                'regenHP'             => 'regenHP — HP recovered at the start of each turn for duration turns',
                'restoration'         => 'restoration — restores both HP and CP each turn',
                'hpcp_up'             => 'hpcp_up — raises maximum HP and CP values for duration',
                'recovery_hp_cp_buff' => 'recovery_hp_cp_buff — combined HP+CP recovery buff',
                'plus_extra_hp'       => 'plus_extra_hp — grants a temporary extra HP pool on top of current max HP',
                'shukaku_blessing'    => 'shukaku_blessing — special Shukaku beast blessing healing effect',
            ],

            'Buffs' => [
                'power_up'               => 'power_up — increases caster\'s outgoing damage (calc_type: percent)',
                'protection'             => 'protection — reduces incoming damage by % for duration',
                'plus_protection'        => 'plus_protection — enhanced damage reduction (stronger than protection)',
                'damage_absorption'      => 'damage_absorption — absorbs a set flat amount of damage before HP loss begins',
                'concentration'          => 'concentration — increases accuracy and critical hit rate',
                'reflexes'               => 'reflexes — increases dodge / evasion chance',
                'increase_agility'       => 'increase_agility — boosts speed and evasion stats',
                'meditation'             => 'meditation — recovers CP each turn while active',
                'peace'                  => 'peace — reduces the duration of incoming debuffs',
                'serene_mind'            => 'serene_mind — mental resistance, reduces debuff effectiveness',
                'preserve'               => 'preserve — prevents active buffs from expiring this turn',
                'preservation'           => 'preservation — buff protection variant',
                'boundless'              => 'boundless — temporarily removes stat caps for massive output',
                'energize'               => 'energize — increases CP generation each turn',
                'self_love'              => 'self_love — self-healing buff restoring HP over time',
                'debuff_resist'          => 'debuff_resist — gives a % chance to resist incoming debuffs',
                'increase_buff_duration' => 'increase_buff_duration — extends the duration of all currently active buffs',
                'strengthen_special'     => 'strengthen_special — boosts the damage output of special skills',
                'attention'              => 'attention — forces enemies to focus all attacks on the caster (taunt)',
                'rage'                   => 'rage — massive damage boost but reduces defenses as a trade-off',
                'bloodlust'              => 'bloodlust — lifesteal: caster recovers HP equal to a % of damage dealt',
                'bloodfeed'              => 'bloodfeed — caster gains HP when an enemy is defeated',
                'embrace'                => 'embrace — protective embrace buff shielding the target from damage',
                'excitation'             => 'excitation — excited power state boosting multiple stats simultaneously',
                'kyubi_cloak'            => 'kyubi_cloak — nine-tails chakra cloak granting a large power buff',
                'lightning_armor'        => 'lightning_armor — lightning-infused armor buff that damages attackers',
                'liquidation_armor'      => 'liquidation_armor — water-based armor buff with evasion bonus',
                'cp_shield'              => 'cp_shield — absorbs incoming damage using CP instead of HP',
                'cp_shield_and_increase_purify' => 'cp_shield_and_increase_purify — CP shield that also boosts purify effectiveness',
                'shadow'                 => 'shadow — shadow clone or shadow-mimic defensive effect',
                'stealth'                => 'stealth — caster becomes harder to target or detect by enemies',
            ],

            'Drains & Reductions' => [
                'cp_drain'               => 'cp_drain — drains target\'s CP each turn (calc_type: number or percent)',
                'current_cp_drain'       => 'current_cp_drain — drains a portion of target\'s current CP immediately',
                'current_hp_drain'       => 'current_hp_drain — drains a portion of target\'s current HP immediately',
                'drain_HpCp'             => 'drain_HpCp — drains both HP and CP simultaneously',
                'reduce_cp'              => 'reduce_cp — immediately reduces target\'s CP by amount',
                'reduce_hp'              => 'reduce_hp — immediately reduces target\'s HP by amount',
                'reduceCP'               => 'reduceCP — alternate CP reduction server handler',
                'reduce_hp_as_damage'    => 'reduce_hp_as_damage — HP reduction that counts as damage (triggers on-damage effects)',
                'reduce_hp_cp'           => 'reduce_hp_cp — reduces both HP and CP values in one effect',
                'insta_reduce_max_hp'    => 'insta_reduce_max_hp — permanently lowers target\'s maximum HP for the battle',
                'insta_reduce_max_cp'    => 'insta_reduce_max_cp — permanently lowers target\'s maximum CP for the battle',
                'insta_consume_all_cp'   => 'insta_consume_all_cp — instantly drains 100% of target\'s current CP',
                'instant_reduce_hp'      => 'instant_reduce_hp — instant HP reduction (no damage type)',
                'instant_reduce_hp_attacker' => 'instant_reduce_hp_attacker — reduces HP of whoever is attacking',
                'dec_cp_attacker'        => 'dec_cp_attacker — reduces attacker\'s CP when they strike',
                'reduce_hp_on_attention' => 'reduce_hp_on_attention — HP drain that triggers when attention debuff is active',
                'theft'                  => 'theft — steals active buffs from the target and transfers them to the caster',
                'disperse'               => 'disperse — strips and removes all active buffs from the target',
                'purify'                 => 'purify — removes all debuffs from the target (combine with target: self)',
                'cp_cost'                => 'cp_cost — modifies the CP cost of the target\'s skills',
                'negate'                 => 'negate — negates the next incoming skill or effect entirely',
            ],

            'Cooldown Effects' => [
                'add_cooldown_player'    => 'add_cooldown_player — adds turns to all of target\'s active skill cooldowns',
                'rapid_cooldown'         => 'rapid_cooldown — reduces all of caster\'s active cooldowns each turn',
                'set_all_cooldown'       => 'set_all_cooldown — forces all target cooldowns to a specific value',
                'increase_fire_cd'       => 'increase_fire_cd — extends cooldowns of all target\'s fire-type skills',
                'increase_wind_cd'       => 'increase_wind_cd — extends cooldowns of all target\'s wind-type skills',
                'increase_water_cd'      => 'increase_water_cd — extends cooldowns of all target\'s water-type skills',
                'increase_earth_cd'      => 'increase_earth_cd — extends cooldowns of all target\'s earth-type skills',
                'increase_lightning_cd'  => 'increase_lightning_cd — extends cooldowns of all target\'s lightning-type skills',
                'reduce_wind_cd'         => 'reduce_wind_cd — reduces cooldowns of wind-type skills by amount',
                'bleeding_on_reduce_wind_cd' => 'bleeding_on_reduce_wind_cd — inflicts bleeding whenever wind cooldowns are reduced',
            ],

            'Counter & Reactive' => [
                'counter_effect' => 'counter_effect — reflects a portion of damage/effects back to the attacker',
                'reactive_force' => 'reactive_force — triggers a counter-action automatically when attacked',
                'attacker_bleeding' => 'attacker_bleeding — inflicts bleeding on whoever attacks the caster',
                'internal_injury_on_plus_protection' => 'internal_injury_on_plus_protection — triggers internal_injury when target has plus_protection active',
                'weaken_on_plus_extra_hp' => 'weaken_on_plus_extra_hp — applies weaken when target has plus_extra_hp active',
                'critical_buff_n_received_stun' => 'critical_buff_n_received_stun — grants a buff on critical hit, stuns when caster is hit',
                'critical_on_heavy_voltage' => 'critical_on_heavy_voltage — guarantees critical hit when heavy_voltage debuff is active',
            ],

            'Special & Unique' => [
                'senjutsu_mark'      => 'senjutsu_mark — applies a sage power mark enabling bonus sage effects',
                'senju_mark'         => 'senju_mark — alternate senjutsu mark variant',
                'instant_kill'       => 'instant_kill — kills target instantly if their HP is below amount % threshold',
                'random_debuff'      => 'random_debuff — applies a random debuff chosen from the server\'s predefined list',
                'random_s16'         => 'random_s16 — applies a random effect from the S16 effect set',
                'sensation'          => 'sensation — unique sensory disruption effect',
                'sacrifice_self_health' => 'sacrifice_self_health — caster sacrifices own HP to gain a power boost',
                'sacrifice_self_health_chance' => 'sacrifice_self_health_chance — chance-based HP self-sacrifice',
                'tolerance'          => 'tolerance — target builds resistance to repeated identical effects',
                'aqua_regia'         => 'aqua_regia — acid/corrosive debuff that eats through defenses',
                'conduction'         => 'conduction — conducts elemental damage to connected targets',
                'covid'              => 'covid — contagion effect that spreads to nearby targets',
                'hanyaoni'           => 'hanyaoni — half-demon power (massive stat boost with unique rules)',
                'heavy_voltage'      => 'heavy_voltage — heavy lightning charge with stun chance',
                'inquisitor'         => 'inquisitor — investigator effect that reveals hidden stats or effects',
                'fire_wall'          => 'fire_wall — creates a fire wall dealing damage to any target that passes',
                'flaming'            => 'flaming — fire aura that deals damage to all attackers',
                'emberstep_demonic'  => 'emberstep_demonic — fire+demon hybrid trail damage',
                'chill'              => 'chill — cold slowdown effect with a chance to freeze',
                'cosmic_flame'       => 'cosmic_flame — cosmic fire that partially ignores elemental resistance',
                'cannot_reduced_cp'  => 'cannot_reduced_cp — prevents the target\'s CP from being reduced by any means',
                'decrease_combustion_chance' => 'decrease_combustion_chance — reduces the chance of fire-based effects triggering',
                'decrease_purify_active' => 'decrease_purify_active — reduces the effectiveness of purify effects on the target',
            ],
        ];
    }
}