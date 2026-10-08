<?php

namespace App\Filament\Resources\Exhibitors;

use App\Filament\Resources\Exhibitors\Pages\ManageExhibitors;
use App\Models\Exhibitor;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Exhibitors of the current event (F9): photo, description and the one category they compete in.
 */
class ExhibitorResource extends Resource
{
    protected static ?string $model = Exhibitor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                Textarea::make('short_description')
                    ->required()
                    ->maxLength(280)
                    ->rows(3)
                    ->helperText('Shown on the voting card. Keep it to one or two sentences.'),
                FileUpload::make('photo_path')
                    ->label('Photo')
                    ->image()
                    ->imageEditor()
                    ->disk(config('voting.photos_disk'))
                    ->directory('exhibitors')
                    ->visibility('public')
                    ->maxSize(4096),
                Select::make('category_id')
                    ->label('Category')
                    ->relationship(
                        'category',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('event_id', Filament::getTenant()->getKey())->orderBy('sort_order'),
                    )
                    ->preload()
                    ->required()
                    // Votes are tied to the category; the database also blocks this change.
                    ->disabled(fn (?Exhibitor $record): bool => (bool) $record?->votes()->exists())
                    ->helperText(fn (?Exhibitor $record): string => $record?->votes()->exists()
                        ? 'Locked: this exhibitor already has votes in this category.'
                        : 'Each exhibitor competes in exactly one category.'),
                Toggle::make('is_active')->label('Active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo_path')->label('')->disk(config('voting.photos_disk'))->circular(),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('category.name')->badge()->label('Category')->sortable(),
                TextColumn::make('votes_count')->counts('votes')->label('Votes'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->hidden(fn (Exhibitor $record): bool => $record->votes()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExhibitors::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
