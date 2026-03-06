<?php

namespace App\Filament\Resources\Mails\Schemas;

use App\Models\Character;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MailForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('character_id')
                ->label('Character')
                ->options(
                    Character::with('user')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($c) => [
                            $c->id => $c->name . ' (' . ($c->user?->username ?? 'no user') . ')',
                        ])
                )
                ->searchable()
                ->required(),

            TextInput::make('title')
                ->required()
                ->default('Admin Mail')
                ->maxLength(255),

            TextInput::make('sender')
                ->default('Admin')
                ->maxLength(255),

            Textarea::make('body')
                ->rows(4)
                ->nullable(),

            TagsInput::make('rewards')
                ->label('Rewards')
                ->placeholder('e.g. wpn_219')
                ->helperText('Press Enter after each: gold_50000  tokens_10  wpn_219  hair_01')
                ->nullable(),

            Hidden::make('type')->default('system'),
            Hidden::make('viewed')->default(false),
            Hidden::make('claimed')->default(false),
        ]);
    }
}
