<?php

namespace App\Filament\Resources\XPLevels\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class XPLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('level')
                    ->label('Level')
                    ->numeric()
                    ->disabled(),
                TextInput::make('character_xp')
                    ->label('Character XP Required')
                    ->helperText('Total accumulated XP threshold to reach this level.')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                TextInput::make('pet_xp')
                    ->label('Pet XP Required')
                    ->helperText('Total accumulated XP threshold for pets to reach this level.')
                    ->required()
                    ->numeric()
                    ->minValue(0),
            ]);
    }
}
