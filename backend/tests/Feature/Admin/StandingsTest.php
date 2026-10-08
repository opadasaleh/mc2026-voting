<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\Vote;
use App\Services\Results\Standings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['slug' => 'mc2026', 'name' => 'Maker Collective 2026']);
        $this->category = Category::factory()->for($this->event)->create(['name' => 'People']);
    }

    public function test_ranks_by_votes_with_shared_ranks_for_ties_and_includes_zero_vote_exhibitors(): void
    {
        [$alpha, $bravo, $charlie, $delta] = $this->enter(['Alpha', 'Bravo', 'Charlie', 'Delta']);
        $this->votesFor($charlie, 3);
        $this->votesFor($alpha, 1);
        $this->votesFor($bravo, 1);

        $results = app(Standings::class)->forEvent($this->event);

        $this->assertSame(['slug' => 'mc2026', 'name' => 'Maker Collective 2026'], $results['event']);
        $this->assertSame(5, $results['total_votes']);
        $this->assertSame(5, $results['total_voters']);
        $this->assertSame(
            [['Charlie', 1, 3], ['Alpha', 2, 1], ['Bravo', 2, 1], ['Delta', 4, 0]],
            array_map(fn ($row) => [$row['name'], $row['rank'], $row['votes']], $results['categories'][0]['standings']),
        );
        $this->assertSame(5, $results['categories'][0]['total_votes']);
    }

    public function test_votes_for_a_removed_exhibitor_still_count_but_removed_exhibitors_without_votes_are_hidden(): void
    {
        [$kept, $removedWithVotes, $removedWithout] = $this->enter(['Kept', 'Removed with votes', 'Removed without']);
        $this->votesFor($removedWithVotes, 2);
        $removedWithVotes->delete();
        $removedWithout->update(['is_active' => false]);

        $names = array_column(app(Standings::class)->forEvent($this->event)['categories'][0]['standings'], 'name');

        $this->assertSame(['Removed with votes', 'Kept'], $names);
    }

    public function test_other_events_are_not_counted(): void
    {
        [$mine] = $this->enter(['Mine']);
        $this->votesFor($mine, 1);

        $other = Event::factory()->create();
        $otherCategory = Category::factory()->for($other)->create();
        $otherExhibitor = Exhibitor::factory()->inCategory($otherCategory)->create();
        $registration = EventRegistration::factory()->for($other)->create();
        Vote::create(['event_id' => $other->id, 'visitor_id' => $registration->visitor_id, 'category_id' => $otherCategory->id, 'exhibitor_id' => $otherExhibitor->id]);

        $results = app(Standings::class)->forEvent($this->event);

        $this->assertSame(1, $results['total_votes']);
        $this->assertCount(1, $results['categories']);
    }

    /**
     * @param  list<string>  $names
     * @return list<Exhibitor>
     */
    private function enter(array $names): array
    {
        return array_map(fn (string $name) => Exhibitor::factory()->inCategory($this->category)->create(['name' => $name]), $names);
    }

    private function votesFor(Exhibitor $exhibitor, int $count): void
    {
        foreach (range(1, $count) as $i) {
            $registration = EventRegistration::factory()->for($this->event)->create();
            Vote::create([
                'event_id' => $this->event->id,
                'visitor_id' => $registration->visitor_id,
                'category_id' => $this->category->id,
                'exhibitor_id' => $exhibitor->id,
            ]);
        }
    }
}
