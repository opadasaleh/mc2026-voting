<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\Visitor;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * The anti-fraud rules that the database itself enforces, independent of the API.
 */
class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    // Postgres SQLSTATE codes.
    private const UNIQUE = '23505';

    private const FOREIGN_KEY = '23503';

    private const CHECK = '23514';

    public function test_a_visitor_gets_one_vote_per_category(): void
    {
        [$event, $category, $first, $second, $registration] = $this->votingSetup();

        $this->vote($registration, $category, $first);

        $this->assertRejectedByDatabase(fn () => $this->vote($registration, $category, $second), self::UNIQUE);
        $this->assertSame(1, Vote::count());
    }

    public function test_a_visitor_can_vote_once_in_each_category(): void
    {
        [$event, $category, $first, , $registration] = $this->votingSetup();
        $other = Category::factory()->for($event)->create();
        $inOther = Exhibitor::factory()->inCategory($other)->create();

        $this->vote($registration, $category, $first);
        $this->vote($registration, $other, $inOther);

        $this->assertSame(2, Vote::count());
    }

    public function test_a_vote_must_target_an_exhibitor_of_that_category(): void
    {
        [$event, $category, , , $registration] = $this->votingSetup();
        $elsewhere = Exhibitor::factory()->inCategory(Category::factory()->for($event)->create())->create();

        $this->assertRejectedByDatabase(fn () => $this->vote($registration, $category, $elsewhere), self::FOREIGN_KEY);
    }

    public function test_a_vote_requires_a_registration_for_that_event(): void
    {
        [, $category, $exhibitor] = $this->votingSetup();
        $elsewhere = EventRegistration::factory()->create();

        $this->assertRejectedByDatabase(fn () => $this->vote($elsewhere, $category, $exhibitor), self::FOREIGN_KEY);
    }

    public function test_a_vote_cannot_claim_another_event(): void
    {
        [, $category, $exhibitor, , $registration] = $this->votingSetup();
        $otherEvent = Event::factory()->create();

        $this->assertRejectedByDatabase(fn () => $this->vote($registration, $category, $exhibitor, $otherEvent->id), self::FOREIGN_KEY);
    }

    public function test_an_exhibitor_cannot_be_put_in_a_category_of_another_event(): void
    {
        [, $category] = $this->votingSetup();
        $otherEvent = Event::factory()->create();

        $this->assertRejectedByDatabase(fn () => Exhibitor::factory()->for($otherEvent)->create(['category_id' => $category->id]), self::FOREIGN_KEY);
    }

    public function test_an_exhibitor_without_votes_can_change_category(): void
    {
        [$event, , $exhibitor] = $this->votingSetup();
        $other = Category::factory()->for($event)->create();

        $exhibitor->update(['category_id' => $other->id]);

        $this->assertSame($other->id, $exhibitor->fresh()->category_id);
    }

    public function test_an_exhibitor_with_votes_cannot_be_moved_to_another_category(): void
    {
        [$event, $category, $exhibitor, , $registration] = $this->votingSetup();
        $other = Category::factory()->for($event)->create();
        $this->vote($registration, $category, $exhibitor);

        $this->assertRejectedByDatabase(fn () => $exhibitor->update(['category_id' => $other->id]), self::FOREIGN_KEY);
    }

    public function test_an_event_has_at_most_three_categories(): void
    {
        $event = Event::factory()->create();
        Category::factory()->for($event)->count(3)->create();

        $this->expectException(LogicException::class);

        Category::factory()->for($event)->create();
    }

    public function test_votes_are_immutable(): void
    {
        [, $category, $first, $second, $registration] = $this->votingSetup();
        $vote = $this->vote($registration, $category, $first);

        $this->expectException(LogicException::class);

        $vote->update(['exhibitor_id' => $second->id]);
    }

    public function test_a_phone_number_identifies_exactly_one_visitor(): void
    {
        $visitor = Visitor::factory()->create();

        $this->assertRejectedByDatabase(fn () => Visitor::factory()->create(['phone_hash' => $visitor->phone_hash]), self::UNIQUE);
    }

    public function test_events_reject_an_unknown_access_mode(): void
    {
        $this->assertRejectedByDatabase(fn () => Event::factory()->create(['access_mode' => 'anywhere']), self::CHECK);
    }

    public function test_events_reject_a_window_that_closes_before_it_opens(): void
    {
        $this->assertRejectedByDatabase(fn () => Event::factory()->create([
            'opens_at' => now(),
            'closes_at' => now()->subHour(),
        ]), self::CHECK);
    }

    /**
     * @return array{0: Event, 1: Category, 2: Exhibitor, 3: Exhibitor, 4: EventRegistration}
     */
    private function votingSetup(): array
    {
        $event = Event::factory()->votingOpen()->create();
        $category = Category::factory()->for($event)->create();
        [$first, $second] = Exhibitor::factory()->inCategory($category)->count(2)->create()->all();
        $registration = EventRegistration::factory()->for($event)->create();

        return [$event, $category, $first, $second, $registration];
    }

    private function vote(EventRegistration $registration, Category $category, Exhibitor $exhibitor, ?int $eventId = null): Vote
    {
        return Vote::create([
            'event_id' => $eventId ?? $category->event_id,
            'visitor_id' => $registration->visitor_id,
            'category_id' => $category->id,
            'exhibitor_id' => $exhibitor->id,
        ]);
    }

    /**
     * Runs the write in a savepoint so the test's own transaction stays usable.
     */
    private function assertRejectedByDatabase(callable $write, string $sqlState): void
    {
        try {
            DB::transaction($write);
        } catch (QueryException $e) {
            $this->assertSame($sqlState, $e->getCode(), $e->getMessage());

            return;
        }

        $this->fail('Expected the database to reject the write.');
    }
}
