<?php

namespace App\Filament\Resources\Giveaways\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GiveawayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Giveaway Details')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),

                    DateTimePicker::make('ends_at')
                        ->label('Ends At')
                        ->required()
                        ->helperText('When the giveaway closes and no new participants can join.'),
                ]),

            Section::make('Prizes')
                ->description('Item IDs awarded to winners. Format: item_id or item_id:qty (e.g. wpn_219, material_874:5). Up to 5 are shown in the UI.')
                ->schema([
                    TagsInput::make('prizes')
                        ->label('')
                        ->placeholder('e.g. wpn_219 or material_874:5')
                        ->helperText('Press Enter after each prize. Max 5 are displayed in the game UI.'),
                ]),

            Section::make('Entry Requirements')
                ->description('Players must meet ALL requirements to join. Supported types: level, pvp_battles, pvp_wins, rank (1=Genin, 2=Chunin, 3=Jounin, 4=Special Jounin, 5=Kage).')
                ->schema([
                    Repeater::make('requirements')
                        ->label('')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('name')
                                    ->label('Display Name')
                                    ->placeholder('e.g. Level 30+')
                                    ->required(),

                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'level'       => 'Level',
                                        'pvp_battles' => 'PvP Battles Played',
                                        'pvp_wins'    => 'PvP Wins',
                                        'rank'        => 'Rank (numeric)',
                                    ])
                                    ->required(),

                                TextInput::make('value')
                                    ->label('Required Value')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                            ]),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add Requirement')
                        ->collapsible(),
                ]),

            Section::make('Winners')
                ->description('Set after drawing. Once winners are added here the client shows the "ended" view. Draw randomly from participants using the participant list, then enter them here.')
                ->schema([
                    Repeater::make('winners')
                        ->label('')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('id')
                                    ->label('Character ID')
                                    ->numeric()
                                    ->required(),

                                TextInput::make('name')
                                    ->label('Character Name')
                                    ->required(),
                            ]),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add Winner')
                        ->collapsible(),
                ]),
        ]);
    }
}
