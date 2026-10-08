<?php

namespace Tests\Feature\Voting;

use App\Models\Category;
use App\Models\Event;
use App\Models\Exhibitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * GET /events, /events/{event}, /events/{event}/categories (F1).
 */
class PublicReadTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->votingOpen()->create([
            'slug' => 'mc2026',
            'name' => 'Maker Collective 2026',
            'closes_at' => '2026-11-14 18:00:00',
        ]);
    }

    public function test_events_lists_only_active_events(): void
    {
        Event::factory()->create(['slug' => 'archived', 'is_active' => false]);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'mc2026')
            ->assertJsonPath('data.0.voting.status', 'open')
            ->assertJsonPath('data.0.voting.closes_at', '2026-11-14T18:00:00Z');
    }

    public function test_an_event_shows_its_voting_status_and_server_time(): void
    {
        $this->travelTo('2026-11-14 10:00:00');
        Category::factory()->for($this->event)->count(2)->create();

        $this->getJson('/api/v1/events/mc2026')
            ->assertOk()
            ->assertExactJson(['data' => [
                'slug' => 'mc2026',
                'name' => 'Maker Collective 2026',
                'description' => null,
                'voting' => ['status' => 'open', 'opens_at' => null, 'closes_at' => '2026-11-14T18:00:00Z'],
                'categories_count' => 2,
                'server_time' => '2026-11-14T10:00:00Z',
            ]]);
    }

    public function test_categories_list_active_exhibitors_in_their_one_category_with_photo_urls(): void
    {
        Storage::fake('public');
        config(['voting.photos_disk' => 'public']);

        $first = Category::factory()->for($this->event)->create(['name' => 'People', 'sort_order' => 1]);
        $second = Category::factory()->for($this->event)->create(['name' => 'Innovation', 'sort_order' => 2]);
        Category::factory()->for($this->event)->create(['is_active' => false]);
        Exhibitor::factory()->inCategory($first)->create(['name' => 'Robo Arm', 'photo_path' => 'exhibitors/robo.jpg']);
        Exhibitor::factory()->inCategory($first)->create(['name' => 'Hidden', 'is_active' => false]);
        Exhibitor::factory()->inCategory($second)->create(['name' => 'Drone Kit']);

        $response = $this->getJson('/api/v1/events/mc2026/categories')->assertOk();

        $this->assertSame(['People', 'Innovation'], array_column($response->json('data'), 'name'));
        $this->assertSame(['Robo Arm'], array_column($response->json('data.0.exhibitors'), 'name'));
        $this->assertSame(['Drone Kit'], array_column($response->json('data.1.exhibitors'), 'name'));
        $this->assertStringEndsWith('exhibitors/robo.jpg', $response->json('data.0.exhibitors.0.photo_url'));
        $this->assertNull($response->json('data.1.exhibitors.0.photo_url'));
    }

    public function test_the_shared_venue_ip_is_not_throttled_on_the_event_list(): void
    {
        config(['voting.rate_limits.offsite_ip_per_minute' => 3]);
        $this->event->update(['allowed_cidrs' => ['203.0.113.0/24']]);

        foreach (range(1, 10) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->getJson('/api/v1/events')->assertOk();
        }

        foreach (range(1, 3) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson('/api/v1/events')->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson('/api/v1/events')->assertTooManyRequests();
    }
}
