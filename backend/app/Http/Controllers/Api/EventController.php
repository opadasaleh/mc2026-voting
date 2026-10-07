<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Exhibitor;
use Illuminate\Http\JsonResponse;

/**
 * Public reads for the voting page (F1): no token, no on-site gate.
 */
class EventController extends Controller
{
    /**
     * GET /events: active events.
     */
    public function index(): JsonResponse
    {
        $events = Event::where('is_active', true)->orderByDesc('created_at')->get();

        return response()->json(['data' => $events->map(fn (Event $event) => [
            'slug' => $event->slug,
            'name' => $event->name,
            'description' => $event->description,
            'voting' => $this->voting($event),
        ])->all()]);
    }

    /**
     * GET /events/{event}: name, voting status and window.
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json(['data' => [
            'slug' => $event->slug,
            'name' => $event->name,
            'description' => $event->description,
            'voting' => $this->voting($event),
            'categories_count' => $event->categories()->where('is_active', true)->count(),
            'server_time' => now()->toIso8601ZuluString(),
        ]]);
    }

    /**
     * GET /events/{event}/categories: active categories with their active exhibitors.
     */
    public function categories(Event $event): JsonResponse
    {
        $categories = $event->categories()
            ->where('is_active', true)
            ->with(['exhibitors' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->get();

        return response()->json(['data' => $categories->map(fn (Category $category) => [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'exhibitors' => $category->exhibitors->map(fn (Exhibitor $exhibitor) => [
                'id' => $exhibitor->id,
                'name' => $exhibitor->name,
                'short_description' => $exhibitor->short_description,
                'photo_url' => $exhibitor->photoUrl(),
            ])->all(),
        ])->all()]);
    }

    /**
     * @return array{status: string, opens_at: ?string, closes_at: ?string}
     */
    private function voting(Event $event): array
    {
        return [
            'status' => $event->votingStatus(),
            'opens_at' => $event->opens_at?->toIso8601ZuluString(),
            'closes_at' => $event->closes_at?->toIso8601ZuluString(),
        ];
    }
}
