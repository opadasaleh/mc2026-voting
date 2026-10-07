<?php

namespace App\Services\OnSite;

use App\Models\Event;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether a request comes from the event venue (F11): the rule is
 * venue Wi-Fi only, i.e. the client's public IP must be inside one of the
 * event's allowed_cidrs. A phone cannot fake its public IP, unlike GPS.
 *
 * The client IP must be the visitor's real address: Laravel only honours
 * X-Forwarded-For from configured trusted proxies (config/voting.php).
 */
class VenueAccess
{
    public function check(Event $event, ?string $ip): AccessDecision
    {
        return $this->ipAllowed($event, $ip)
            ? new AccessDecision(true, null)
            : new AccessDecision(false, AccessDecision::OUTSIDE_IP_RANGE);
    }

    public function ipAllowed(Event $event, ?string $ip): bool
    {
        $cidrs = $event->allowed_cidrs ?? [];

        if ($ip === null || $ip === '' || $cidrs === []) {
            return false;
        }

        // An IPv4 client seen through a dual-stack socket arrives as ::ffff:a.b.c.d.
        if (str_starts_with(strtolower($ip), '::ffff:') && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = substr($ip, 7);
        }

        return IpUtils::checkIp($ip, $cidrs);
    }
}
