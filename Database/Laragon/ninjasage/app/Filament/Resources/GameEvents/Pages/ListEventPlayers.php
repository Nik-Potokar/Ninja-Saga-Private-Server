<?php

namespace App\Filament\Resources\GameEvents\Pages;

use App\Filament\Resources\GameEvents\EventAnalyticsResource;
use App\Models\CharacterEventData;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListEventPlayers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = EventAnalyticsResource::class;

    protected string $view = 'filament.resources.game-events.pages.list-event-players';

    public string $eventKey = '';

    public function mount(): void
    {
        $this->eventKey = request()->query('event_key', '');
    }

    public function getTitle(): string
    {
        return $this->eventKey ? "Players — {$this->eventKey}" : 'Player Detail';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Analytics')
                ->icon('heroicon-o-arrow-left')
                ->url(EventAnalyticsResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CharacterEventData::query()
                    ->when($this->eventKey, fn (Builder $q) => $q->where('event_key', $this->eventKey))
            )
            ->columns([
                Tables\Columns\TextColumn::make('character_id')
                    ->label('Character ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('event_key')
                    ->label('Event Key')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: (bool) $this->eventKey),

                Tables\Columns\TextColumn::make('battles')
                    ->label('Battles')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('energy')
                    ->label('Energy Remaining')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('bought')
                    ->label('Package Bought')
                    ->boolean(),

                Tables\Columns\TextColumn::make('milestones_claimed')
                    ->label('Milestones Claimed')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? count($state) . ' claimed' : '0 claimed')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_key')
                    ->label('Event Key')
                    ->options(
                        fn () => CharacterEventData::query()
                            ->distinct()
                            ->orderBy('event_key')
                            ->pluck('event_key', 'event_key')
                            ->toArray()
                    )
                    ->default($this->eventKey ?: null),
            ])
            ->defaultSort('battles', 'desc');
    }
}
