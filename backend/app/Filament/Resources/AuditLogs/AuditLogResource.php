<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the audit log: this event's entries plus global ones (admin logins).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 9;

    // Scoped manually below so global entries (event_id null) are included.
    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user')
            ->where(fn (Builder $query) => $query
                ->where('event_id', Filament::getTenant()?->getKey())
                ->orWhereNull('event_id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, H:i:s', config('voting.display_timezone'))
                    ->sortable(),
                TextColumn::make('user.username')->label('Admin')->placeholder('-'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('meta')
                    ->label('Details')
                    ->formatStateUsing(fn ($state): string => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                    ->wrap(),
                TextColumn::make('ip')->label('IP')->color('gray'),
            ])
            ->filters([
                SelectFilter::make('action')->options(fn (): array => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}
