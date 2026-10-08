<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Results\Standings;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\VotingQr;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The current event's dashboard: voting open/close (F10), results export (F13),
 * visitor export (F14), results reset and the voting QR code (F4). Every
 * voting/results action is audit-logged.
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
                ->modalDescription(fn (): string => $this->event()->allowed_cidrs === []
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
            ActionGroup::make([
                Action::make('exportResults')
                    ->label('Export results (CSV)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->exportResults()),
                Action::make('exportVisitors')
                    ->label('Export visitors (CSV)')
                    ->icon(Heroicon::OutlinedUsers)
                    ->requiresConfirmation()
                    ->modalHeading('Export visitor personal data?')
                    ->modalDescription('The file contains names and phone numbers. Store it securely and only use it for Makerspace outreach. This export is recorded in the audit log.')
                    ->modalSubmitActionLabel('Export')
                    ->action(fn (): StreamedResponse => $this->exportVisitors()),
                Action::make('resetResults')
                    ->label('Reset results')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->disabled(fn (): bool => $this->event()->voting_enabled)
                    ->tooltip(fn (): ?string => $this->event()->voting_enabled ? 'Close voting first.' : null)
                    ->modalHeading('Delete all votes for this event?')
                    ->modalDescription('This cannot be undone. Export the results first. Visitors stay registered and verified.')
                    ->modalSubmitActionLabel('Delete all votes')
                    ->schema([
                        TextInput::make('confirmation')
                            ->label(fn (): string => 'Type "'.$this->event()->slug.'" to confirm')
                            ->required()
                            ->in(fn (): array => [$this->event()->slug])
                            ->validationMessages(['in' => 'Type the event slug exactly.']),
                    ])
                    ->action(fn () => $this->resetResults()),
            ])
                ->label('Results')
                ->icon(Heroicon::OutlinedChartBar)
                ->button()
                ->color('gray'),
            ActionGroup::make([
                Action::make('showQrCode')
                    ->label('Show QR code')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Voting QR code')
                    ->modalContent(fn () => view('filament.voting-qr', [
                        'svg' => VotingQr::svg($this->event()),
                        'url' => VotingQr::url($this->event()),
                        'isLocal' => (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:|/|$)#', VotingQr::url($this->event())),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('downloadQrSvg')
                    ->label('Download for print (SVG)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): StreamedResponse => $this->downloadQr('svg', 'image/svg+xml', VotingQr::svg($this->event()))),
                Action::make('downloadQrPng')
                    ->label('Download image (PNG)')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->action(fn (): StreamedResponse => $this->downloadQr('png', 'image/png', VotingQr::png($this->event()))),
            ])
                ->label('QR code')
                ->icon(Heroicon::OutlinedQrCode)
                ->button()
                ->color('gray'),
        ];
    }

    private function downloadQr(string $extension, string $contentType, string $content): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print ($content),
            $this->event()->slug.'-voting-qr.'.$extension,
            ['Content-Type' => $contentType],
        );
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

    private function exportResults(): StreamedResponse
    {
        $event = $this->event();
        $results = app(Standings::class)->forEvent($event);

        Audit::record('results.exported', $event, ['total_votes' => $results['total_votes']]);

        $rows = [];
        foreach ($results['categories'] as $category) {
            foreach ($category['standings'] as $row) {
                $rows[] = [$category['name'], $row['rank'], $row['name'], $row['votes']];
            }
        }

        return Csv::download($this->filename('results'), ['Category', 'Rank', 'Exhibitor', 'Votes'], $rows);
    }

    private function exportVisitors(): StreamedResponse
    {
        $event = $this->event();
        $votesByVisitor = $event->votes()
            ->select('visitor_id', DB::raw('count(*) as total'))
            ->groupBy('visitor_id')
            ->pluck('total', 'visitor_id');

        $registrations = EventRegistration::with('visitor')
            ->where('event_id', $event->getKey())
            ->orderBy('created_at')
            ->get();

        Audit::record('visitors.exported', $event, ['rows' => $registrations->count()]);

        $timezone = config('voting.display_timezone');
        $rows = $registrations->map(fn (EventRegistration $registration) => [
            $registration->full_name,
            $registration->visitor->phone_encrypted,
            $registration->created_at?->timezone($timezone)->format('Y-m-d H:i'),
            $registration->phone_verified_at?->timezone($timezone)->format('Y-m-d H:i') ?? 'not verified',
            (int) ($votesByVisitor[$registration->visitor_id] ?? 0),
        ]);

        return Csv::download(
            $this->filename('visitors'),
            ['Full name', 'Phone', "Registered ({$timezone})", "Verified ({$timezone})", 'Votes cast'],
            $rows,
        );
    }

    private function resetResults(): void
    {
        $event = $this->event();
        $deleted = DB::transaction(fn (): int => $event->votes()->delete());

        Audit::record('votes.reset', $event, ['deleted' => $deleted]);

        Notification::make()
            ->title("Deleted {$deleted} votes")
            ->success()
            ->send();
    }

    private function filename(string $kind): string
    {
        return sprintf('%s-%s-%s.csv', $this->event()->slug, $kind, now(config('voting.display_timezone'))->format('Ymd-Hi'));
    }

    private function event(): Event
    {
        /** @var Event */
        return Filament::getTenant();
    }
}
