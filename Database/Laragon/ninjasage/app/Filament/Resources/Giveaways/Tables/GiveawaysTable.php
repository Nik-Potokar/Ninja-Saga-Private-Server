<?php

namespace App\Filament\Resources\Giveaways\Tables;

use App\Models\GiveawayParticipant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GiveawaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('participants_count')
                    ->label('Participants')
                    ->getStateUsing(fn ($record) => GiveawayParticipant::where('giveaway_id', $record->id)->count())
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->getStateUsing(fn ($record) => $record->isActive())
                    ->boolean(),

                IconColumn::make('has_winners')
                    ->label('Winners Set')
                    ->getStateUsing(fn ($record) => $record->hasWinners())
                    ->boolean(),
            ])
            ->defaultSort('ends_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}