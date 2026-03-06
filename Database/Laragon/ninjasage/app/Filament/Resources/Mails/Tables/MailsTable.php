<?php

namespace App\Filament\Resources\Mails\Tables;

use App\Models\CharacterMail;
use App\Services\Amf\RewardGrantService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('character.name')
                    ->label('Character')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sender')
                    ->sortable(),
                TextColumn::make('rewards')
                    ->label('Rewards')
                    ->badge()
                    ->color(fn (string $state): string => match(true) {
                        str_starts_with($state, 'gold_')   => 'warning',
                        str_starts_with($state, 'tokens_') => 'info',
                        str_starts_with($state, 'tp_')     => 'info',
                        str_starts_with($state, 'xp_')     => 'primary',
                        str_starts_with($state, 'wpn_')    => 'danger',
                        str_starts_with($state, 'hair_')   => 'primary',
                        str_starts_with($state, 'pet_')    => 'success',
                        str_starts_with($state, 'skill_')  => 'warning',
                        default                            => 'gray',
                    })
                    ->separator(','),
                IconColumn::make('viewed')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('claimed')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('claimRewards')
                    ->label('Claim')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->visible(fn ($record): bool => !$record->claimed && !empty($record->rewards))
                    ->requiresConfirmation()
                    ->modalHeading('Claim rewards for this character?')
                    ->modalDescription(fn ($record) => 'This will grant all rewards from "' . $record->title . '" to ' . ($record->character?->name ?? 'unknown') . '.')
                    ->action(function ($record): void {
                        if ($record->claimed) return;

                        $char = $record->character;
                        if (!$char) return;

                        $grantService = new RewardGrantService();
                        foreach ($record->rewards ?? [] as $rewardStr) {
                            if (is_string($rewardStr) && $rewardStr !== '') {
                                $grantService->grant($char, $rewardStr);
                            }
                        }

                        $record->claimed = true;
                        $record->viewed  = true;
                        $record->save();

                        Notification::make()
                            ->title('Rewards granted to ' . $char->name)
                            ->success()
                            ->send();
                    }),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Resend this mail?')
                    ->modalDescription(fn ($record) => 'A fresh copy of "' . $record->title . '" will be sent to ' . ($record->character?->name ?? 'unknown') . ' (viewed & claimed reset).')
                    ->action(function ($record): void {
                        CharacterMail::create([
                            'character_id' => $record->character_id,
                            'title'        => $record->title,
                            'sender'       => $record->sender,
                            'body'         => $record->body,
                            'type'         => $record->type,
                            'rewards'      => $record->rewards,
                            'viewed'       => false,
                            'claimed'      => false,
                        ]);

                        Notification::make()
                            ->title('Mail resent to ' . ($record->character?->name ?? 'unknown'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}