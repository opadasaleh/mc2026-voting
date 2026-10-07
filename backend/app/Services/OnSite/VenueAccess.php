<?php

namespace App\Services\OnSite;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether a request comes from the event venue (F11), from the event's
 * database settings: access_mode, allowed_cidrs (venue Wi-Fi public IPs) and geofence.
 *
 * The client IP must be the visitor's real address: Laravel only honours
 * X-Forwarded-For from configured trusted proxies (config/voting.php).
 */
class VenueAccess
{
    private const EARTH_RADIUS_M = 6_371_000;

    /**
     * Check a request; lat/lng come from the query string or body when the event uses a geofence.
     */
    public function checkRequest(Event $event, Request $request): AccessDecision
    {
        $location = Validator::make($request->only('lat', 'lng'), [
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ])->validate();

        return $this->check(
            $event,
            $request->ip(),
            isset($location['lat']) ? (float) $location['lat'] : null,
            isset($location['lng']) ? (float) $location['lng'] : null,
        );
    }

    public function check(Event $event, ?string $ip, ?float $lat, ?float $lng): AccessDecision
    {
        $mode = $event->access_mode;
        $ipAllowed = $this->ipAllowed($event, $ip);
        $hasLocation = $lat !== null && $lng !== null;
        $insideGeofence = $hasLocation && $this->insideGeofence($event, $lat, $lng);

        $onSite = match ($mode) {
            'ip' => $ipAllowed,
            'geo' => $insideGeofence,
            'either' => $ipAllowed || $insideGeofence,
            'both' => $ipAllowed && $insideGeofence,
        };

        $requiresLocation = match ($mode) {
            'geo', 'both' => true,
            'either' => ! $ipAllowed,
            'ip' => false,
        };

        $reason = match (true) {
            $onSite => null,
            $mode === 'ip', $mode === 'both' && ! $ipAllowed => AccessDecision::OUTSIDE_IP_RANGE,
            ! $hasLocation => AccessDecision::LOCATION_REQUIRED,
            default => AccessDecision::OUTSIDE_GEOFENCE,
        };

        return new AccessDecision($onSite, $mode, $requiresLocation, $reason);
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

    private function insideGeofence(Event $event, float $lat, float $lng): bool
    {
        $fence = $event->geofence;

        if (! isset($fence['lat'], $fence['lng'], $fence['radius_m'])) {
            return false;
        }

        return $this->distanceInMetres($lat, $lng, (float) $fence['lat'], (float) $fence['lng']) <= (float) $fence['radius_m'];
    }

    private function distanceInMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
