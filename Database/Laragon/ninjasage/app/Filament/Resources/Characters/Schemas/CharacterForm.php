<?php

namespace App\Filament\Resources\Characters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs as SchemaTabs;
use Filament\Schemas\Schema;

class CharacterForm
{
    public static function configure(Schema $schema): Schema
    {
        $elementOptions = [
            0 => 'None',
            1 => 'Wind',
            2 => 'Fire',
            3 => 'Lightning',
            4 => 'Earth',
            5 => 'Water',
        ];

        return $schema
            ->components([
                SchemaTabs::make('Character Management')
                    ->tabs([
                        SchemaTabs\Tab::make('Profile')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Basic Information')
                                    ->schema([
                                        Select::make('user_id')
                                            ->relationship('user', 'username')
                                            ->required()
                                            ->searchable(),
                                        TextInput::make('name')
                                            ->required(),
                                        Select::make('gender')
                                            ->options([0 => 'Male', 1 => 'Female'])
                                            ->required()
                                            ->default(0),
                                        Select::make('rank')
                                            ->options([
                                                1 => 'Genin',
                                                3 => 'Chunin',
                                                5 => 'Jounin',
                                                7 => 'Special Jounin',
                                                8 => 'Sannin',
                                                10 => 'Kage',
                                            ])
                                            ->required()
                                            ->default(1),
                                        TextInput::make('level')->numeric()->default(1),
                                        TextInput::make('xp')->numeric()->default(0),
                                        TextInput::make('gold')->numeric()->default(1000),
                                        TextInput::make('prestige')->numeric()->default(0),
                                    ])->columns(2),
                            ]),

                        SchemaTabs\Tab::make('Attributes & Talents')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Section::make('Elements')
                                    ->schema([
                                        Select::make('element_1')->options($elementOptions)->default(0),
                                        Select::make('element_2')->options($elementOptions)->default(0),
                                        Select::make('element_3')->options($elementOptions)->default(0),
                                    ])->columns(3),
                                Section::make('Stats')
                                    ->schema([
                                        TextInput::make('tp')->label('Talent Points')->numeric()->default(0),
                                        TextInput::make('ss')->label('Sage Souls')->numeric()->default(0),
                                        TextInput::make('talent_1')->numeric()->default(0),
                                        TextInput::make('talent_2')->numeric()->default(0),
                                        TextInput::make('talent_3')->numeric()->default(0),
                                        TextInput::make('point_wind')->numeric()->default(0),
                                        TextInput::make('point_fire')->numeric()->default(0),
                                        TextInput::make('point_lightning')->numeric()->default(0),
                                        TextInput::make('point_water')->numeric()->default(0),
                                        TextInput::make('point_earth')->numeric()->default(0),
                                        TextInput::make('point_free')->numeric()->default(0),
                                    ])->columns(3),
                            ]),

                        SchemaTabs\Tab::make('Style & Equipment')
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                TextInput::make('hair_style'),
                                TextInput::make('hair_color'),
                                TextInput::make('skin_color'),
                                TextInput::make('equipment_weapon'),
                                TextInput::make('equipment_back'),
                                TextInput::make('equipment_clothing'),
                                TextInput::make('equipment_accessory'),
                                TextInput::make('equipment_pet'),
                                Textarea::make('equipment_skills')->columnSpanFull(),
                            ])->columns(2),

                        SchemaTabs\Tab::make('Game Progress')
                            ->icon('heroicon-o-map')
                            ->schema([
                                TextInput::make('chunin_exam_progress')->default('1,0,0,0,0'),
                                Toggle::make('chunin_claimed'),
                                TextInput::make('jounin_exam_progress')->default('1,0,0,0,0'),
                                Toggle::make('jounin_claimed'),
                                TextInput::make('special_jounin_exam_progress')->default('1,0,0,0,0,0,0,0,0,0,0,0,0'),
                                Toggle::make('special_jounin_claimed'),
                                TextInput::make('ninja_tutor_exam_progress')->default('1,0,0,0,0,0'),
                                Toggle::make('ninja_tutor_claimed'),
                                TextInput::make('hunting_house_tries'),
                                TextInput::make('hunting_house_date'),
                                TextInput::make('eudemon_garden_tries'),
                                TextInput::make('eudemon_garden_date'),
                                Textarea::make('claimed_welcome_rewards')->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpanFull()
            ]);
    }
}
