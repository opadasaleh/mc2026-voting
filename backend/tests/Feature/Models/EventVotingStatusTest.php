<?php

namespace Tests\Feature\Models;

use App\Models\Event;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventVotingStatusTest extends TestCase
{
    /**
     * @return array<string, array{0: bool, 1: ?string, 2: ?string, 3: string}>
     */
    public static function cases(): array
    {
        return [
            'switch off' => [false, null, null, 'closed'],
            'switch on, no window' => [true, null, null, 'open'],
            'before the window' => [true, '2026-11-14 10:00', null, 'scheduled'],
            'inside the window' => [true, '2026-11-14 08:00', '2026-11-14 18:00', 'open'],
            'after the window' => [true, '2026-11-14 08:00', '2026-11-14 09:00', 'closed'],
            'exactly at closing time' => [true, null, '2026-11-14 09:00', 'closed'],
            'switch off inside the window' => [false, '2026-11-14 08:00', '2026-11-14 18:00', 'closed'],
        ];
    }

    #[DataProvider('cases')]
    public function test_voting_status(bool $enabled, ?string $opensAt, ?string $closesAt, string $expected): void
    {
        $event = new Event(['voting_enabled' => $enabled, 'opens_at' => $opensAt, 'closes_at' => $closesAt]);
        $now = Carbon::parse('2026-11-14 09:00');

        $this->assertSame($expected, $event->votingStatus($now));
        $this->assertSame($expected === 'open', $event->isVotingOpen($now));
    }
}
