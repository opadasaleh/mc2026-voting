<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Vote;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /events/{event}/me and POST /events/{event}/auth/logout (visitor token).
 */
class VisitorController extends Controller
{
    /**
     * Who the visitor is and which categories they already voted in (F3).
     */
    public function me(Request $request, Event $event): JsonResponse
    {
        /** @var Visitor $visitor */
        $visitor = $request->user();

        $votes = Vote::where('event_id', $event->getKey())
            ->where('visitor_id', $visitor->getKey())
            ->orderBy('created_at')
            ->get(['category_id', 'exhibitor_id', 'created_at']);

        $remaining = $event->categories()
            ->where('is_active', true)
            ->whereNotIn('id', $votes->pluck('category_id'))
            ->pluck('id');

        return response()->json(['data' => [
            'visitor' => ['full_name' => $request->attributes->get('registration')->full_name],
            'votes' => $votes->map(fn (Vote $vote) => [
                'category_id' => $vote->category_id,
                'exhibitor_id' => $vote->exhibitor_id,
                'voted_at' => $vote->created_at->toIso8601ZuluString(),
            ])->all(),
            'remaining_category_ids' => $remaining->all(),
        ]]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
