<?php

namespace Tests\Feature\OnSite;

use App\Models\Event;
use App\Services\OnSite\AccessDecision;
use App\Services\OnSite\VenueAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VenueAccessTest extends TestCase
{
    private const VENUE_IPV4 = '203.0.113.10';

    private const VENUE_IPV6 = '2001:db8:1::42';

    private const OFFSITE_IP = '198.51.100.7';

    // Venue geofence: 300 m around a point in Amman.
    private const INSIDE = [31.9539, 35.9106];

    private const OUTSIDE = [31.9900, 35.9106]; // ~4 km north

    public function test_wifi_only_admits_the_venue_ipv4_range(): void
    {
        $this->assertDecision('ip', self::VENUE_IPV4, null, true, false, null);
    }

    public function test_wifi_only_admits_the_venue_ipv6_range(): void
    {
        $this->assertDecision('ip', self::VENUE_IPV6, null, true, false, null);
    }

    public function test_wifi_only_admits_an_ipv4_client_seen_through_a_dual_stack_socket(): void
    {
        $this->assertDecision('ip', '::ffff:'.self::VENUE_IPV4, null, true, false, null);
    }

    public function test_wifi_only_rejects_other_ips_even_with_a_location_inside_the_venue(): void
    {
        $this->assertDecision('ip', self::OFFSITE_IP, self::INSIDE, false, false, AccessDecision::OUTSIDE_IP_RANGE);
    }

    public function test_wifi_only_rejects_everyone_when_no_venue_ip_is_configured(): void
    {
        $event = $this->event('ip', cidrs: []);

        $this->assertFalse(app(VenueAccess::class)->check($event, self::VENUE_IPV4, null, null)->onSite);
    }

    public function test_a_missing_or_malformed_ip_is_rejected(): void
    {
        $access = app(VenueAccess::class);

        $this->assertFalse($access->ipAllowed($this->event('ip'), null));
        $this->assertFalse($access->ipAllowed($this->event('ip'), 'not-an-ip'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?array{0: float, 1: float}, 3: bool, 4: bool, 5: ?string}>
     */
    public static function geofenceModes(): array
    {
        return [
            'geo: inside' => ['geo', self::OFFSITE_IP, self::INSIDE, true, true, null],
            'geo: outside' => ['geo', self::VENUE_IPV4, self::OUTSIDE, false, true, AccessDecision::OUTSIDE_GEOFENCE],
            'geo: no location' => ['geo', self::VENUE_IPV4, null, false, true, AccessDecision::LOCATION_REQUIRED],
            'either: venue IP, no location needed' => ['either', self::VENUE_IPV4, null, true, false, null],
            'either: mobile data, inside' => ['either', self::OFFSITE_IP, self::INSIDE, true, true, null],
            'either: mobile data, no location' => ['either', self::OFFSITE_IP, null, false, true, AccessDecision::LOCATION_REQUIRED],
            'either: mobile data, outside' => ['either', self::OFFSITE_IP, self::OUTSIDE, false, true, AccessDecision::OUTSIDE_GEOFENCE],
            'both: venue IP and inside' => ['both', self::VENUE_IPV4, self::INSIDE, true, true, null],
            'both: venue IP, no location' => ['both', self::VENUE_IPV4, null, false, true, AccessDecision::LOCATION_REQUIRED],
            'both: venue IP, outside' => ['both', self::VENUE_IPV4, self::OUTSIDE, false, true, AccessDecision::OUTSIDE_GEOFENCE],
            'both: wrong IP' => ['both', self::OFFSITE_IP, self::INSIDE, false, true, AccessDecision::OUTSIDE_IP_RANGE],
        ];
    }

    /**
     * @param  ?array{0: float, 1: float}  $location
     */
    #[DataProvider('geofenceModes')]
    public function test_geofence_modes(string $mode, string $ip, ?array $location, bool $onSite, bool $requiresLocation, ?string $reason): void
    {
        $this->assertDecision($mode, $ip, $location, $onSite, $requiresLocation, $reason);
    }

    /**
     * @param  ?array{0: float, 1: float}  $location
     */
    private function assertDecision(string $mode, string $ip, ?array $location, bool $onSite, bool $requiresLocation, ?string $reason): void
    {
        $decision = app(VenueAccess::class)->check($this->event($mode), $ip, $location[0] ?? null, $location[1] ?? null);

        $this->assertSame(
            ['on_site' => $onSite, 'mode' => $mode, 'requires_location' => $requiresLocation, 'reason' => $reason],
            $decision->toArray(),
        );
    }

    /**
     * @param  list<string>  $cidrs
     */
    private function event(string $mode, array $cidrs = ['203.0.113.0/24', '2001:db8:1::/48']): Event
    {
        return new Event([
            'access_mode' => $mode,
            'allowed_cidrs' => $cidrs,
            'geofence' => ['lat' => self::INSIDE[0], 'lng' => self::INSIDE[1], 'radius_m' => 300],
        ]);
    }
}
