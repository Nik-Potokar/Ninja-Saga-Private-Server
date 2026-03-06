<?php

namespace App\Filament\Pages;

use App\Models\ChatMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

class WorldChat extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'World Chat';
    protected static string|\UnitEnum|null $navigationGroup = 'Admin Tools';
    protected static ?string $title = 'World Chat';
    protected string $view = 'filament.pages.world-chat';

    /** The channel currently displayed in the table. */
    public string $activeChannel = 'global';

    // ─── Channel switcher ────────────────────────────────────────────────────

    /**
     * Distinct channels that have messages, global first, then clans alphabetically.
     */
    public function availableChannels(): array
    {
        return ChatMessage::query()
            ->select('channel')
            ->distinct()
            ->orderByRaw("CASE WHEN channel = 'global' THEN 0 ELSE 1 END, channel ASC")
            ->pluck('channel')
            ->toArray();
    }

    public function selectChannel(string $channel): void
    {
        $this->activeChannel = $channel;
        $this->resetTable();
    }

    // ─── Table ───────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            // Closure so Filament re-evaluates when $activeChannel changes.
            ->query(fn () => ChatMessage::query()
                ->where('channel', $this->activeChannel)
                ->latest('id')
            )
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('character_name')
                    ->label('Character')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('character_level')
                    ->label('Lv.')
                    ->sortable(),

                TextColumn::make('message')
                    ->searchable()
                    ->wrap()
                    ->limit(200),

                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->poll('5s');
    }

    // ─── Header actions ──────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('announce')
                ->label('Send Announcement')
                ->icon('heroicon-o-megaphone')
                ->color('warning')
                ->modalHeading('Broadcast Announcement')
                ->modalDescription('Shows an instant pop-up to all players currently connected to the chat.')
                ->form([
                    Forms\Components\Select::make('channel')
                        ->label('Target')
                        ->options([
                            'global' => 'Global Chat',
                            'clan'   => 'All Clan Chats',
                        ])
                        ->default('global')
                        ->required(),

                    Forms\Components\Textarea::make('text')
                        ->label('Message')
                        ->required()
                        ->maxLength(300)
                        ->rows(3)
                        ->placeholder('Server will restart in 10 minutes…'),
                ])
                ->action(function (array $data): void {
                    $this->broadcastAnnouncement($data['channel'], $data['text']);
                }),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function broadcastAnnouncement(string $channel, string $text): void
    {
        $url    = rtrim(env('CHAT_INTERNAL_URL', 'http://127.0.0.1:3002'), '/') . '/admin/announce';
        $secret = env('CHAT_ADMIN_SECRET', '');

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Admin-Secret' => $secret])
                ->post($url, compact('channel', 'text'));

            if ($response->successful()) {
                Notification::make()->title('Announcement sent')->success()->send();
            } else {
                $hint = $response->status() === 401
                    ? 'Secret mismatch — restart the chat server after updating its .env.'
                    : ('HTTP ' . $response->status() . ': ' . $response->body());

                Notification::make()
                    ->title('Chat server rejected the request')
                    ->body($hint)
                    ->danger()
                    ->persistent()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not reach the chat server')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}