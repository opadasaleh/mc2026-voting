<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * At-a-glance state of the current event, refreshed every 10 seconds.
 */
class VotingStatusOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        /** @var Event $event */
        $event = Filament::getTenant();
        $status = $event->votingStatus();
        $venueIps = count($event->allowed_cidrs ?? []);

        return [
            Stat::make('Voting', ucfirst($status))
                ->color(match ($status) {
                    'open' => 'success',
                    'scheduled' => 'warning',
                    default => 'danger',
                })
                ->description(match ($status) {
                    'scheduled' => 'Opens '.$event->opens_at?->timezone(config('voting.display_timezone'))->format('M j, H:i'),
                    'open' => $event->closes_at ? 'Closes '.$event->closes_at->timezone(config('voting.display_timezone'))->format('M j, H:i') : 'No closing time set',
                    default => 'Not accepting votes',
                }),
            Stat::make('Venue Wi-Fi IPs', $venueIps)
                ->color($venueIps === 0 ? 'danger' : 'gray')
                ->description($venueIps === 0 ? 'None set: nobody can pass the on-site check' : 'Only visitors on the venue Wi-Fi can vote'),
            Stat::make('Categories / exhibitors', $event->categories()->where('is_active', true)->count().' / '.$event->exhibitors()->where('is_active', true)->count()),
            Stat::make('Verified visitors', $event->registrations()->whereNotNull('phone_verified_at')->count()),
            Stat::make('Votes cast', $event->votes()->count()),
        ];
    }
}
