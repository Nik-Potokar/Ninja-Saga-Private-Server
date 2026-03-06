<?php

namespace App\Filament\Resources\Pets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class PetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('pet_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                FileUpload::make('icon')
                    ->directory('pets')
                    ->image(),
                TextInput::make('swf')
                    ->required(),
                TextInput::make('price_gold')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('price_tokens')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('premium')
                    ->required()
                    ->default(false),
            ]);
    }
}
