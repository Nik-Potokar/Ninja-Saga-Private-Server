<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('account_type')
                    ->options([
                        0 => 'Free User',
                        1 => 'Premium User',
                    ])
                    ->required()
                    ->default(0),
                TextInput::make('tokens')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->hiddenOn('edit'), // Don't show on edit unless we add logic to hash it
            ]);
    }
}