<?php

namespace Tests\Feature\Voting;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\Vote;
use App\Services\Results\Standings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /events/{event}/votes (F2, F3, F12).
 */
class VoteTest extends TestCase
{
    use RefreshDatabase;

    private const VENUE_IP = '203.0.113.10';

    private Event $event;

    private Category $people;

    private Category $innovation;

    private Exhibitor $roboArm;

    private Exhibitor $greenhouse;

    private Exhibitor $drone;

    private EventRegistration $registration;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->votingOpen()->create(['slug' => 'mc2026', 'allowed_cidrs' => ['203.0.113.0/24']]);
        $this->people = Category::factory()->for($this->event)->create(['sort_order' => 1]);
        $this->innovation = Category::factory()->for($this->event)->create(['sort_order' => 2]);
        $this->roboArm = Exhibitor::factory()->inCategory($this->people)->create();
        $this->greenhouse = Exhibitor::factory()->inCategory($this->people)->create();
        $this->drone = Exhibitor::factory()->inCategory($this->innovation)->create();

        $this->registration = EventRegistration::factory()->for($this->event)->create();
        $this->token = $this->tokenFor($this->registration, $this->event);
    }

    public function test_a_verified_visitor_votes_once_in_a_category(): void
    {
        $this->vote($this->people, $this->roboArm)
            ->assertCreated()
            ->assertJsonPath('data.category_id', $this->people->id)
            ->assertJsonPath('data.exhibitor_id', $this->roboArm->id)
            ->assertJsonPath('data.remaining_category_ids', [$this->innovation->id]);

        $this->assertSame(1, Vote::count());
        $this->assertSame(self::VENUE_IP, Vote::sole()->ip);
    }

    public function test_resending_the_same_vote_is_a_harmless_retry(): void
    {
        $first = $this->vote($this->people, $this->roboArm)->assertCreated();

        $this->vote($this->people, $this->roboArm)
            ->assertOk()
            ->assertJsonPath('data.voted_at', $first->json('data.voted_at'));

        $this->assertSame(1, Vote::count());
    }

    public function test_a_vote_cannot_be_changed(): void
    {
        $this->vote($this->people, $this->roboArm);

        $this->vote($this->people, $this->greenhouse)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_VOTED')
            ->assertJsonPath('error.details', ['category_id' => $this->people->id, 'exhibitor_id' => $this->roboArm->id]);

        $this->assertSame($this->roboArm->id, Vote::sole()->exhibitor_id);
    }

    public function test_a_visitor_votes_in_every_category_and_is_then_done(): void
    {
        $this->vote($this->people, $this->roboArm)->assertCreated();
        $this->vote($this->innovation, $this->drone)
            ->assertCreated()
            ->assertJsonPath('data.remaining_category_ids', []);

        $results = app(Standings::class)->forEvent($this->event);
        $this->assertSame(2, $results['total_votes']);
        $this->assertSame(1, $results['total_voters']);
    }

    public function test_the_exhibitor_must_be_in_that_category(): void
    {
        $this->vote($this->people, $this->drone)->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_EXHIBITOR_FOR_CATEGORY');

        $this->greenhouse->update(['is_active' => false]);
        $this->vote($this->people, $this->greenhouse)->assertJsonPath('error.code', 'INVALID_EXHIBITOR_FOR_CATEGORY');

        $this->roboArm->delete();
        $this->vote($this->people, $this->roboArm)->assertJsonPath('error.code', 'INVALID_EXHIBITOR_FOR_CATEGORY');

        $this->assertSame(0, Vote::count());
    }

    public function test_categories_of_other_events_and_inactive_categories_are_unknown(): void
    {
        $foreign = Category::factory()->create();
        $foreignExhibitor = Exhibitor::factory()->inCategory($foreign)->create();
        $this->vote($foreign, $foreignExhibitor)->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->people->update(['is_active' => false]);
        $this->vote($this->people, $this->roboArm)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_no_votes_while_voting_is_closed_or_scheduled(): void
    {
        $this->event->update(['voting_enabled' => false]);
        $this->vote($this->people, $this->roboArm)->assertForbidden()->assertJsonPath('error.details.status', 'closed');

        $this->event->update(['voting_enabled' => true, 'opens_at' => now()->addHour()]);
        $this->vote($this->people, $this->roboArm)->assertJsonPath('error.code', 'VOTING_CLOSED')->assertJsonPath('error.details.status', 'scheduled');
    }

    public function test_votes_only_from_the_venue_wifi(): void
    {
        $this->vote($this->people, $this->roboArm, ip: '198.51.100.7')->assertForbidden()->assertJsonPath('error.code', 'OFF_SITE');
    }

    public function test_the_token_is_checked_before_the_wifi(): void
    {
        $this->vote($this->people, $this->roboArm, ip: '198.51.100.7', token: null)
            ->assertUnauthorized()
            ->assertJsonPath('error.details.reason', 'missing');
    }

    public function test_a_token_from_another_event_cannot_vote_here(): void
    {
        $other = Event::factory()->votingOpen()->create(['slug' => 'other']);
        $token = $this->tokenFor(EventRegistration::factory()->for($other)->create(['visitor_id' => $this->registration->visitor_id]), $other);

        $this->vote($this->people, $this->roboArm, token: $token)
            ->assertUnauthorized()
            ->assertJsonPath('error.details.reason', 'wrong_event');
    }

    public function test_bad_input_fails_validation(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::VENUE_IP])
            ->postJson('/api/v1/events/mc2026/votes', ['category_id' => 'x'], ['Authorization' => 'Bearer '.$this->token])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['category_id', 'exhibitor_id']]]]);
    }

    public function test_votes_are_rate_limited_per_token(): void
    {
        config(['voting.rate_limits.votes_per_token_per_minute' => 2]);

        $this->vote($this->people, $this->roboArm)->assertCreated();
        $this->vote($this->people, $this->roboArm)->assertOk();
        $this->vote($this->people, $this->roboArm)->assertTooManyRequests()->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    private function vote(Category $category, Exhibitor $exhibitor, string $ip = self::VENUE_IP, ?string $token = 'default'): TestResponse
    {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer '.($token === 'default' ? $this->token : $token)];

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/events/mc2026/votes', ['category_id' => $category->id, 'exhibitor_id' => $exhibitor->id], $headers);
    }

    private function tokenFor(EventRegistration $registration, Event $event): string
    {
        return $registration->visitor->createToken('visitor:'.$event->slug, ['vote', 'event:'.$event->id], now()->addHours(12))->plainTextToken;
    }
}
