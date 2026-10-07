<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\OnSite\VenueAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/events/{event}/access-check: lets the frontend show the
 * "No access" page before the visitor types anything. UX only; the
 * on-site middleware enforces the same rule on every write endpoint.
 */
class AccessCheckController extends Controller
{
    public function __invoke(Request $request, Event $event, VenueAccess $venueAccess): JsonResponse
    {
        $decision = $venueAccess->check($event, $request->ip());

        return response()->json([
            'data' => $decision->toArray() + ['venue_wifi_name' => $event->venue_wifi_name],
        ]);
    }
}
