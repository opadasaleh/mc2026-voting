<?php

namespace Tests\Feature\OnSite;

use App\Models\Event;
use App\Services\OnSite\AccessDecision;
use App\Services\OnSite\VenueAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The on-site rule is venue Wi-Fi only: the client's public IP must be in allowed_cidrs.
 */
class VenueAccessTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function ips(): array
    {
        return [
            'venue IPv4' => ['203.0.113.10', true],
            'venue IPv6' => ['2001:db8:1::42', true],
            'venue IPv4 through a dual-stack socket' => ['::ffff:203.0.113.10', true],
            'mobile data / anywhere else' => ['198.51.100.7', false],
            'other IPv6' => ['2001:db8:2::1', false],
            'malformed' => ['not-an-ip', false],
        ];
    }

    #[DataProvider('ips')]
    public function test_only_the_venue_wifi_ranges_are_on_site(string $ip, bool $onSite): void
    {
        $decision = app(VenueAccess::class)->check($this->event(), $ip);

        $this->assertSame(
            ['on_site' => $onSite, 'reason' => $onSite ? null : AccessDecision::OUTSIDE_IP_RANGE],
            $decision->toArray(),
        );
    }

    public function test_nobody_is_on_site_when_no_venue_ip_is_configured(): void
    {
        $this->assertFalse(app(VenueAccess::class)->check($this->event(cidrs: []), '203.0.113.10')->onSite);
    }

    public function test_a_missing_ip_is_rejected(): void
    {
        $this->assertFalse(app(VenueAccess::class)->ipAllowed($this->event(), null));
    }

    /**
     * @param  list<string>  $cidrs
     */
    private function event(array $cidrs = ['203.0.113.0/24', '2001:db8:1::/48']): Event
    {
        return new Event(['allowed_cidrs' => $cidrs]);
    }
}
