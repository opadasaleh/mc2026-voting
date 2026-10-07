<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->disabled(fn (): bool => $this->isAtLimit())
                ->tooltip(fn (): ?string => $this->isAtLimit()
                    ? 'An event can have at most '.Category::maxPerEvent().' categories.'
                    : null),
        ];
    }

    private function isAtLimit(): bool
    {
        return Category::where('event_id', Filament::getTenant()->getKey())->count() >= Category::maxPerEvent();
    }
}
