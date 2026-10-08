<?php

namespace Tests\Feature\Voting;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only the configured frontend origins may call the API from a browser.
 */
class CorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => ['http://localhost:3000']]);
        Event::factory()->create(['slug' => 'mc2026']);
    }

    public function test_the_frontend_origin_may_call_the_api(): void
    {
        $this->getJson('/api/v1/events/mc2026', ['Origin' => 'http://localhost:3000'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
    }

    public function test_the_preflight_for_a_vote_allows_the_authorization_header(): void
    {
        $this->options('/api/v1/events/mc2026/votes', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_other_origins_are_not_allowed(): void
    {
        // The browser blocks the response unless the header names the calling origin.
        $allowed = $this->getJson('/api/v1/events/mc2026', ['Origin' => 'https://evil.example'])
            ->headers->get('Access-Control-Allow-Origin');

        $this->assertNotContains($allowed, ['https://evil.example', '*']);
    }
}
