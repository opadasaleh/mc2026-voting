<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\DemoVotes;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Exhibitor;
use App\Models\Visitor;
use App\Models\Vote;
use App\Services\Results\Standings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoVotesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['slug' => 'mc2026']);
        foreach (Category::factory()->for($this->event)->count(3)->create() as $category) {
            Exhibitor::factory()->inCategory($category)->count(3)->create();
        }
    }

    public function test_it_adds_verified_demo_visitors_and_valid_votes(): void
    {
        $this->artisan('voting:demo-votes', ['--visitors' => 40])->assertSuccessful();

        $this->assertSame(40, EventRegistration::where('registered_ip', DemoVotes::DEMO_IP)->whereNotNull('phone_verified_at')->count());
        $this->assertGreaterThan(40, Vote::count());
        // The database already guarantees one vote per category and matching categories; check the totals add up.
        $results = app(Standings::class)->forEvent($this->event);
        $this->assertSame(Vote::count(), $results['total_votes']);
        $this->assertSame(40, $results['total_voters']);
        $this->assertTrue(AuditLog::where('action', 'demo.votes_added')->exists());
    }

    public function test_clear_removes_only_demo_data(): void
    {
        $real = EventRegistration::factory()->for($this->event)->create();
        $category = $this->event->categories()->first();
        Vote::create([
            'event_id' => $this->event->id,
            'visitor_id' => $real->visitor_id,
            'category_id' => $category->id,
            'exhibitor_id' => $category->exhibitors()->first()->id,
        ]);

        $this->artisan('voting:demo-votes', ['--visitors' => 25])->assertSuccessful();
        $this->artisan('voting:demo-votes', ['--clear' => true])->assertSuccessful();

        $this->assertSame(1, Vote::count());
        $this->assertSame([$real->id], EventRegistration::pluck('id')->all());
        $this->assertSame([$real->visitor_id], Visitor::pluck('id')->all());
    }

    public function test_an_unknown_event_fails(): void
    {
        $this->artisan('voting:demo-votes', ['event' => 'nope'])->assertFailed();
    }
}
