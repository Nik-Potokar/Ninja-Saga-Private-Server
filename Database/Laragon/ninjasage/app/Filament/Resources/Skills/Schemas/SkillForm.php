<?php

namespace App\Filament\Resources\Skills\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('skill_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                FileUpload::make('icon')
                    ->directory('skills')
                    ->image(),
                TextInput::make('level')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('element')
                    ->options([
                        0 => 'None',
                        1 => 'Wind',
                        2 => 'Fire',
                        3 => 'Lightning',
                        4 => 'Earth',
                        5 => 'Water',
                    ])
                    ->required()
                    ->default(0),
                TextInput::make('swf')
                    ->label('SWF Override')
                    ->helperText('Optional. Enter a skill_id whose SWF should be served for this skill (e.g. "skill_59"). Leave blank to serve this skill\'s own SWF file by skill_id.')
                    ->placeholder('e.g. skill_59'),
                Toggle::make('premium')
                    ->required(),
                TextInput::make('price_gold')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('price_tokens')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}