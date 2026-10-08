<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Services\Results\Standings;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Per-category leaderboards for the current event, refreshed every 5 seconds.
 */
class LiveResults extends Widget
{
    protected string $view = 'filament.widgets.live-results';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();

        return [
            'results' => app(Standings::class)->forEvent($event),
            'timezone' => config('voting.display_timezone'),
        ];
    }
}
