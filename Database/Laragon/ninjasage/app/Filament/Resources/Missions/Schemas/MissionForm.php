<?php

namespace App\Filament\Resources\Missions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mission_id')
                    ->required(),
                TextInput::make('req_lvl')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('xp')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('gold')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
