<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Visitor;
use App\Services\Voting\VoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /events/{event}/votes (visitor token + on-site gate).
 * 201 created, 200 for a repeated identical vote (retry after a network drop).
 */
class VoteController extends Controller
{
    public function __invoke(Request $request, Event $event, VoteService $votes): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'exhibitor_id' => ['required', 'integer'],
        ]);

        /** @var Visitor $visitor */
        $visitor = $request->user();
        ['vote' => $vote, 'created' => $created] = $votes->cast($event, $visitor, (int) $data['category_id'], (int) $data['exhibitor_id'], $request->ip());

        return response()->json(['data' => [
            'category_id' => $vote->category_id,
            'exhibitor_id' => $vote->exhibitor_id,
            'voted_at' => $vote->created_at->toIso8601ZuluString(),
            'remaining_category_ids' => $votes->remainingCategoryIds($event, $visitor),
        ]], $created ? 201 : 200);
    }
}
