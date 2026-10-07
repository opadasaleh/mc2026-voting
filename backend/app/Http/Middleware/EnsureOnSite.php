<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\Event;
use App\Services\OnSite\VenueAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The on-site gate for write endpoints (OTP request/verify, votes): rejects with
 * 403 OFF_SITE or LOCATION_REQUIRED unless the request comes from the event venue.
 */
class EnsureOnSite
{
    public function __construct(private readonly VenueAccess $venueAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            throw ApiError::eventNotFound();
        }

        $decision = $this->venueAccess->checkRequest($event, $request);

        if (! $decision->onSite) {
            throw ApiError::notOnSite($decision, $event->venue_wifi_name);
        }

        return $next($request);
    }
}
