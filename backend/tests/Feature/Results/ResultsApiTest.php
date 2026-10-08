<?php

namespace Tests\Feature\Results;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * GET /events/{event}/results and /results/stream for the TV screen (F7, F8).
 */
class ResultsApiTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Category $category;

    private Exhibitor $exhibitor;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->votingOpen()->create(['slug' => 'mc2026', 'allowed_cidrs' => ['203.0.113.0/24']]);
        $this->category = Category::factory()->for($this->event)->create(['name' => 'People']);
        $this->exhibitor = Exhibitor::factory()->inCategory($this->category)->create(['name' => 'Robo Arm']);
        Exhibitor::factory()->inCategory($this->category)->create(['name' => 'Smart Greenhouse']);

        $this->token = $this->event->createDisplayToken('Main hall TV')->plainTextToken;
        config(['voting.results.cache_seconds' => 0]);
    }

    public function test_a_display_token_reads_the_standings_from_anywhere(): void
    {
        $this->vote();

        // The screen need not be on the venue Wi-Fi.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->getJson('/api/v1/events/mc2026/results', ['Authorization' => 'Bearer '.$this->token])
            ->assertOk()
            ->assertJsonPath('data.event.slug', 'mc2026')
            ->assertJsonPath('data.total_votes', 1)
            ->assertJsonPath('data.total_voters', 1)
            ->assertJsonPath('data.voting.status', 'open')
            ->assertJsonPath('data.categories.0.name', 'People')
            ->assertJsonPath('data.categories.0.standings.0.name', 'Robo Arm')
            ->assertJsonPath('data.categories.0.standings.0.rank', 1)
            ->assertJsonPath('data.categories.0.standings.1.votes', 0);
    }

    public function test_the_token_may_come_from_the_query_string_for_event_source(): void
    {
        $this->getJson('/api/v1/events/mc2026/results?token='.urlencode($this->token))->assertOk();
    }

    public function test_using_the_token_marks_the_screen_as_seen(): void
    {
        $this->results()->assertOk();

        $this->assertNotNull(PersonalAccessToken::sole()->last_used_at);
    }

    public function test_only_this_events_display_tokens_are_accepted(): void
    {
        $this->getJson('/api/v1/events/mc2026/results')->assertUnauthorized()->assertJsonPath('error.details.reason', 'missing');
        $this->results('garbage')->assertUnauthorized()->assertJsonPath('error.details.reason', 'invalid');

        $registration = EventRegistration::factory()->for($this->event)->create();
        $visitorToken = $registration->visitor->createToken('visitor:mc2026', ['vote', 'event:'.$this->event->id])->plainTextToken;
        $this->results($visitorToken)->assertUnauthorized()->assertJsonPath('error.details.reason', 'invalid');

        $otherToken = Event::factory()->create(['slug' => 'other'])->createDisplayToken('Other TV')->plainTextToken;
        $this->results($otherToken)->assertUnauthorized()->assertJsonPath('error.details.reason', 'wrong_event');

        $expired = $this->event->createDisplayToken('Old TV', now()->subMinute())->plainTextToken;
        $this->results($expired)->assertUnauthorized()->assertJsonPath('error.details.reason', 'expired');
    }

    public function test_a_display_token_cannot_be_used_as_a_visitor(): void
    {
        $this->getJson('/api/v1/events/mc2026/me', ['Authorization' => 'Bearer '.$this->token])
            ->assertUnauthorized()
            ->assertJsonPath('error.details.reason', 'invalid');
    }

    public function test_a_revoked_token_stops_working(): void
    {
        PersonalAccessToken::sole()->delete();

        $this->results()->assertUnauthorized()->assertJsonPath('error.details.reason', 'invalid');
    }

    public function test_results_are_rate_limited_per_token(): void
    {
        config(['voting.rate_limits.results_per_token_per_minute' => 2]);

        $this->results()->assertOk();
        $this->results()->assertOk();
        $this->results()->assertTooManyRequests()->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    public function test_the_stream_starts_with_a_full_snapshot(): void
    {
        config(['voting.results.stream_max_seconds' => 0]);
        $this->vote();

        $response = $this->get('/api/v1/events/mc2026/results/stream?token='.urlencode($this->token))->assertOk();

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $events = $this->parse($response->streamedContent());
        $this->assertSame(['retry', 'snapshot'], array_column($events, 'type'));
        $this->assertSame(1, $events[1]['data']['total_votes']);
        $this->assertSame('open', $events[1]['data']['voting']['status']);
    }

    public function test_the_stream_sends_changes_and_heartbeats_in_between(): void
    {
        config([
            'voting.results.stream_max_seconds' => 10,
            'voting.results.stream_poll_seconds' => 2,
            'voting.results.stream_heartbeat_seconds' => 4,
        ]);
        Sleep::fake(syncWithCarbon: true);
        $sleeps = 0;
        Sleep::whenFakingSleep(function () use (&$sleeps) {
            if (++$sleeps === 1) {
                $this->vote();
            }
        });

        $events = $this->parse($this->get('/api/v1/events/mc2026/results/stream?token='.urlencode($this->token))->streamedContent());

        $snapshots = array_values(array_filter($events, fn (array $event) => $event['type'] === 'snapshot'));
        $this->assertSame([0, 1], array_map(fn (array $event) => $event['data']['total_votes'], $snapshots), 'A new snapshot only when the tally changes.');
        $this->assertContains('heartbeat', array_column($events, 'type'));
        Sleep::assertSleptTimes(5);
    }

    public function test_the_stream_ends_when_the_token_is_revoked(): void
    {
        config(['voting.results.stream_max_seconds' => 60]);
        Sleep::fake();
        Sleep::whenFakingSleep(fn () => PersonalAccessToken::query()->delete());

        $events = $this->parse($this->get('/api/v1/events/mc2026/results/stream?token='.urlencode($this->token))->streamedContent());

        $this->assertSame(['retry', 'snapshot'], array_column($events, 'type'));
        Sleep::assertSleptTimes(1);
    }

    private function results(?string $token = null): TestResponse
    {
        return $this->getJson('/api/v1/events/mc2026/results', ['Authorization' => 'Bearer '.($token ?? $this->token)]);
    }

    private function vote(): void
    {
        $registration = EventRegistration::factory()->for($this->event)->create();

        Vote::create([
            'event_id' => $this->event->id,
            'visitor_id' => $registration->visitor_id,
            'category_id' => $this->category->id,
            'exhibitor_id' => $this->exhibitor->id,
        ]);
    }

    /**
     * @return list<array{type: string, data?: array<string, mixed>}>
     */
    private function parse(string $stream): array
    {
        $events = [];

        foreach (array_filter(explode("\n\n", $stream)) as $block) {
            $events[] = match (true) {
                str_starts_with($block, 'retry:') => ['type' => 'retry'],
                str_starts_with($block, ': heartbeat') => ['type' => 'heartbeat'],
                str_starts_with($block, "event: snapshot\ndata: ") => [
                    'type' => 'snapshot',
                    'data' => json_decode(substr($block, strlen("event: snapshot\ndata: ")), true),
                ],
            };
        }

        return $events;
    }
}
