<?php

namespace App\Filament\Resources\AdminCommands\Schemas;

use App\Models\AdminCommand;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdminCommandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->rows(2)
                ->nullable(),

            Select::make('command_type')
                ->label('Command Type')
                ->options(AdminCommand::commandTypes())
                ->required()
                ->helperText('give_all_category needs params.category | add_gold/add_tokens need params.amount | set_rank needs params.rank | set_level needs params.level'),

            KeyValue::make('params')
                ->label('Default Parameters')
                ->keyLabel('Key (e.g. category, amount, rank, level)')
                ->valueLabel('Value (e.g. weapon, 50000, 3, 60)')
                ->nullable()
                ->reorderable(false),

            Toggle::make('active')
                ->default(true),
        ]);
    }
}
