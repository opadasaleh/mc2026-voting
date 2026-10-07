<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The current event's dashboard, with the voting open/close switch (F10).
 */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('openVoting')
                ->label('Open voting')
                ->icon(Heroicon::OutlinedPlay)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->event()->allowed_cidrs === [] && $this->event()->access_mode !== 'geo'
                    ? 'Warning: no venue Wi-Fi IPs are set, so nobody will pass the on-site check. Open anyway?'
                    : 'Visitors on the venue Wi-Fi will be able to vote. The optional window in Event settings still applies.')
                ->visible(fn (): bool => ! $this->event()->voting_enabled)
                ->action(fn () => $this->setVoting(true)),
            Action::make('closeVoting')
                ->label('Close voting')
                ->icon(Heroicon::OutlinedStop)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('No more votes will be accepted until voting is opened again.')
                ->visible(fn (): bool => $this->event()->voting_enabled)
                ->action(fn () => $this->setVoting(false)),
        ];
    }

    private function setVoting(bool $open): void
    {
        $event = $this->event();
        $event->update(['voting_enabled' => $open]);

        Audit::record($open ? 'voting.opened' : 'voting.closed', $event);

        Notification::make()
            ->title($open ? 'Voting opened' : 'Voting closed')
            ->success()
            ->send();
    }

    private function event(): Event
    {
        /** @var Event */
        return Filament::getTenant();
    }
}
