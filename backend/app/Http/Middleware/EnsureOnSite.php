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
 * 403 OFF_SITE unless the request comes from the venue Wi-Fi.
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

        $decision = $this->venueAccess->check($event, $request->ip());

        if (! $decision->onSite) {
            throw ApiError::notOnSite($decision, $event->venue_wifi_name);
        }

        return $next($request);
    }
}
