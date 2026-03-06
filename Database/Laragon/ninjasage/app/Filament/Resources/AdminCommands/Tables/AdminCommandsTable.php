<?php

namespace App\Filament\Resources\AdminCommands\Tables;

use App\Models\AdminCommand;
use App\Models\Character;
use App\Services\AdminCommandService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminCommandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('command_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'give_all_skills'        => 'success',
                        'give_all_category'      => 'info',
                        'give_all_hairstyles'    => 'info',
                        'give_all_pets'          => 'warning',
                        'give_all_weapons'       => 'info',
                        'give_all_setitems'      => 'info',
                        'give_all_materialitems' => 'primary',
                        'give_all_accessoryitems'=> 'primary',
                        'give_all_shadowwaritems'=> 'warning',
                        'give_all_packageitems'  => 'info',
                        'give_all_eventitems'    => 'info',
                        'give_all_leaderboarditems'=> 'success',
                        'give_all_essentialitems'=> 'warning',
                        'give_all_dealitems'     => 'danger',
                        'give_all_spendingitems' => 'info',
                        'give_all_crewitems'     => 'info',
                        'give_all_clanitems'     => 'primary',
                        'give_all_backitems'     => 'info',

                        'give_all_available'     => 'success',

                        'add_gold'               => 'warning',
                        'add_tokens'             => 'primary',
                        'set_rank'               => 'danger',
                        'set_level'              => 'danger',
                        default                  => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => AdminCommand::commandTypes()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('params')
                    ->label('Params')
                    ->formatStateUsing(fn ($state): string => $state
                        ? collect($state)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ')
                        : '—'
                    ),
                IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                Action::make('run')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->form([
                        Select::make('character_id')
                            ->label('Target Character')
                            ->options(Character::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (AdminCommand $record, array $data): void {
                        $char = Character::find($data['character_id']);
                        if (!$char) {
                            Notification::make()->title('Character not found')->danger()->send();
                            return;
                        }
                        $result = (new AdminCommandService())->execute($record, $char);
                        if ($result['success']) {
                            Notification::make()->title($result['message'])->success()->send();
                        } else {
                            Notification::make()->title($result['message'])->danger()->send();
                        }
                    })
                    ->visible(fn (AdminCommand $record): bool => $record->active),
                EditAction::make(),
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
