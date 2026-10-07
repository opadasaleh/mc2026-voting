<?php

namespace Tests\Feature\OnSite;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GET /events/{event}/access-check and the `on-site` gate used by the write endpoints.
 */
class OnSiteHttpTest extends TestCase
{
    use RefreshDatabase;

    private const VENUE_IP = '203.0.113.10';

    private const OFFSITE_IP = '198.51.100.7';

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create([
            'slug' => 'mc2026',
            'access_mode' => 'ip',
            'allowed_cidrs' => ['203.0.113.0/24'],
            'venue_wifi_name' => 'MC2026-Guest',
        ]);

        // Stand-in for the real write endpoints (OTP, votes), which use the same gate.
        Route::middleware(['api', 'on-site'])->post('api/v1/events/{event}/_gate-probe', fn () => response()->json(['ok' => true]));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_access_check_says_on_site_for_the_venue_wifi(): void
    {
        $this->fromIp(self::VENUE_IP)->getJson('/api/v1/events/mc2026/access-check')
            ->assertOk()
            ->assertExactJson(['data' => [
                'on_site' => true,
                'mode' => 'ip',
                'requires_location' => false,
                'reason' => null,
                'venue_wifi_name' => 'MC2026-Guest',
            ]]);
    }

    public function test_access_check_says_off_site_with_the_wifi_name_for_the_no_access_page(): void
    {
        $this->fromIp(self::OFFSITE_IP)->getJson('/api/v1/events/mc2026/access-check')
            ->assertOk()
            ->assertJsonPath('data.on_site', false)
            ->assertJsonPath('data.reason', 'OUTSIDE_IP_RANGE')
            ->assertJsonPath('data.venue_wifi_name', 'MC2026-Guest');
    }

    public function test_the_gate_lets_venue_requests_through(): void
    {
        $this->fromIp(self::VENUE_IP)->postJson('/api/v1/events/mc2026/_gate-probe')->assertOk();
    }

    public function test_the_gate_rejects_off_site_requests_with_off_site(): void
    {
        $this->fromIp(self::OFFSITE_IP)->postJson('/api/v1/events/mc2026/_gate-probe')
            ->assertForbidden()
            ->assertExactJson(['error' => [
                'code' => 'OFF_SITE',
                'message' => 'Voting is only available on the event Wi-Fi.',
                'details' => ['reason' => 'OUTSIDE_IP_RANGE', 'venue_wifi_name' => 'MC2026-Guest'],
            ]]);
    }

    public function test_the_gate_asks_for_location_when_the_event_needs_it(): void
    {
        $this->event->update(['access_mode' => 'geo', 'geofence' => ['lat' => 31.95, 'lng' => 35.91, 'radius_m' => 300]]);

        $this->fromIp(self::VENUE_IP)->postJson('/api/v1/events/mc2026/_gate-probe')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'LOCATION_REQUIRED');

        $this->fromIp(self::VENUE_IP)->postJson('/api/v1/events/mc2026/_gate-probe', ['lat' => 31.95, 'lng' => 35.91])
            ->assertOk();
    }

    public function test_a_spoofed_forwarded_for_header_is_ignored(): void
    {
        $this->fromIp(self::OFFSITE_IP)
            ->withHeaders(['X-Forwarded-For' => self::VENUE_IP])
            ->postJson('/api/v1/events/mc2026/_gate-probe')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'OFF_SITE');
    }

    public function test_forwarded_for_is_honoured_from_a_trusted_proxy(): void
    {
        TrustProxies::at(['10.0.0.5']);

        $this->fromIp('10.0.0.5')
            ->withHeaders(['X-Forwarded-For' => self::VENUE_IP])
            ->postJson('/api/v1/events/mc2026/_gate-probe')
            ->assertOk();
    }

    public function test_unknown_and_inactive_events_are_not_found(): void
    {
        $this->event->update(['is_active' => false]);

        foreach (['/api/v1/events/mc2026/access-check', '/api/v1/events/nope/access-check'] as $url) {
            $this->fromIp(self::VENUE_IP)->getJson($url)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'EVENT_NOT_FOUND');
        }
    }

    public function test_invalid_coordinates_fail_validation(): void
    {
        $this->fromIp(self::VENUE_IP)->getJson('/api/v1/events/mc2026/access-check?lat=200&lng=35.9')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['lat']]]]);
    }

    public function test_off_site_ips_are_throttled(): void
    {
        config(['voting.rate_limits.offsite_ip_per_minute' => 3]);

        foreach (range(1, 3) as $i) {
            $this->fromIp(self::OFFSITE_IP)->getJson('/api/v1/events/mc2026/access-check')->assertOk();
        }

        $this->fromIp(self::OFFSITE_IP)->getJson('/api/v1/events/mc2026/access-check')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After');
    }

    public function test_the_shared_venue_ip_is_not_throttled_like_a_single_client(): void
    {
        config(['voting.rate_limits.offsite_ip_per_minute' => 3]);

        // Many visitors behind the venue NAT: far more requests than the off-site limit.
        foreach (range(1, 20) as $i) {
            $this->fromIp(self::VENUE_IP)->getJson('/api/v1/events/mc2026/access-check')->assertOk();
        }
    }

    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }
}
