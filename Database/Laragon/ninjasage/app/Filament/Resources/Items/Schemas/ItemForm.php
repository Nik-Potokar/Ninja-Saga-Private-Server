<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('item_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                FileUpload::make('icon')
                    ->directory('items')
                    ->image(),
                TextInput::make('level')
                    ->required()
                    ->numeric()
                    ->default(1),
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
                Select::make('category')
                    ->options([
                        'weapon' => 'Weapon',
                        'back' => 'Back Item',
                        'accessory' => 'Accessory',
                        'set' => 'Set/Clothing',
                        'hair' => 'Hairstyle',
                        'material' => 'Material',
                        'item' => 'Consumable Item',
                        'essential' => 'Essential',
                    ])
                    ->required(),
            ]);
    }
}