<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Display tokens for this event's TV screens (F7): read-only access to the
 * results API, shown once on creation, revocable at any time.
 */
class DisplayTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTv;

    protected static ?string $navigationLabel = 'TV displays';

    protected static ?string $title = 'TV displays';

    protected static ?int $navigationSort = 8;

    /** The token just created; kept only in this page's state so it can be copied once. */
    public ?string $plainToken = null;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Copy this token now')
                ->key('newToken')
                ->description('It is shown only once. Give it to the TV screen; anyone with it can read this event\'s live results, nothing else.')
                ->icon(Heroicon::OutlinedKey)
                ->visible(fn (): bool => $this->plainToken !== null)
                ->schema([
                    TextEntry::make('token')
                        ->state(fn (): ?string => $this->plainToken)
                        ->copyable()
                        ->fontFamily('mono'),
                    TextEntry::make('streamUrl')
                        ->label('Live stream URL (for testing)')
                        ->state(fn (): ?string => $this->plainToken === null ? null
                            : url('/api/v1/events/'.$this->event()->slug.'/results/stream?token='.urlencode($this->plainToken)))
                        ->copyable()
                        ->fontFamily('mono'),
                ])
                ->footerActions([
                    Action::make('dismissToken')
                        ->label('I have copied it')
                        ->action(fn () => $this->plainToken = null),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => PersonalAccessToken::query()->whereMorphedTo('tokenable', $this->event()))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No TV displays yet')
            ->emptyStateDescription('Create a token for each screen that shows the live results.')
            ->columns([
                TextColumn::make('name')->label('Screen'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, H:i', config('voting.display_timezone')),
                TextColumn::make('last_used_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, H:i', config('voting.display_timezone'))
                    ->placeholder('Never')
                    ->color(fn (PersonalAccessToken $record): ?string => $record->expires_at?->isPast() ? 'danger' : null),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The screen stops receiving results within a few seconds.')
                    ->action(function (PersonalAccessToken $record) {
                        $record->delete();
                        Audit::record('display_token.revoked', $this->event(), ['token_id' => $record->getKey(), 'name' => $record->name]);
                        Notification::make()->title('Token revoked')->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createToken')
                ->label('New display token')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')
                        ->label('Screen name')
                        ->placeholder('Main hall TV')
                        ->required()
                        ->maxLength(60),
                    Select::make('expires_in_days')
                        ->label('Expires after')
                        ->options([1 => '1 day', 7 => '7 days', 30 => '30 days'])
                        ->default(7)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $event = $this->event();
                    $newToken = $event->createDisplayToken($data['name'], now()->addDays((int) $data['expires_in_days']));

                    Audit::record('display_token.created', $event, [
                        'token_id' => $newToken->accessToken->getKey(),
                        'name' => $data['name'],
                        'expires_at' => $newToken->accessToken->expires_at?->toIso8601ZuluString(),
                    ]);

                    $this->plainToken = $newToken->plainTextToken;
                }),
        ];
    }

    private function event(): Event
    {
        /** @var Event */
        return Filament::getTenant();
    }
}
