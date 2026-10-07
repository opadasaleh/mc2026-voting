<?php

namespace App\Services\Results;

use App\Models\Category;
use App\Models\Event;
use App\Models\Exhibitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Per-category standings for an event (F7), in the shape of GET /events/{event}/results.
 *
 * Active exhibitors appear even with 0 votes. Categories and exhibitors that were
 * deactivated or removed still appear if they hold votes, so no vote ever silently
 * disappears from the results. Ties share a rank (1, 1, 3).
 */
class Standings
{
    /**
     * @return array{
     *     event: array{slug: string, name: string},
     *     generated_at: string,
     *     total_voters: int,
     *     total_votes: int,
     *     categories: list<array{id: int, name: string, total_votes: int, standings: list<array{rank: int, exhibitor_id: int, name: string, photo_url: ?string, votes: int}>}>
     * }
     */
    public function forEvent(Event $event): array
    {
        // votes per (category, exhibitor) entry, including entries with none
        $entries = DB::table('category_exhibitor as ce')
            ->leftJoin('votes as v', function ($join) {
                $join->on('v.event_id', '=', 'ce.event_id')
                    ->on('v.category_id', '=', 'ce.category_id')
                    ->on('v.exhibitor_id', '=', 'ce.exhibitor_id');
            })
            ->where('ce.event_id', $event->getKey())
            ->groupBy('ce.category_id', 'ce.exhibitor_id')
            ->select('ce.category_id', 'ce.exhibitor_id', DB::raw('count(v.id) as votes'))
            ->get()
            ->groupBy('category_id');

        $categories = Category::where('event_id', $event->getKey())->orderBy('sort_order')->orderBy('name')->get();
        $exhibitors = Exhibitor::withTrashed()->where('event_id', $event->getKey())->get()->keyBy('id');
        $disk = Storage::disk(config('voting.photos_disk'));

        $result = [];

        foreach ($categories as $category) {
            $rows = collect($entries->get($category->id, []))
                ->map(fn ($entry) => ['exhibitor' => $exhibitors->get($entry->exhibitor_id), 'votes' => (int) $entry->votes])
                ->filter(fn (array $row) => $row['exhibitor'] !== null
                    && ($row['votes'] > 0 || ($row['exhibitor']->is_active && ! $row['exhibitor']->trashed())))
                ->sortBy([['votes', 'desc'], fn (array $a, array $b) => strcasecmp($a['exhibitor']->name, $b['exhibitor']->name)])
                ->values();

            $categoryVotes = $rows->sum('votes');

            if (! $category->is_active && $categoryVotes === 0) {
                continue;
            }

            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'total_votes' => $categoryVotes,
                'standings' => $rows->map(fn (array $row) => [
                    // competition ranking: 1 + number of entries with strictly more votes
                    'rank' => 1 + $rows->where('votes', '>', $row['votes'])->count(),
                    'exhibitor_id' => $row['exhibitor']->id,
                    'name' => $row['exhibitor']->name,
                    'photo_url' => $row['exhibitor']->photo_path ? $disk->url($row['exhibitor']->photo_path) : null,
                    'votes' => $row['votes'],
                ])->all(),
            ];
        }

        return [
            'event' => ['slug' => $event->slug, 'name' => $event->name],
            'generated_at' => now()->toIso8601ZuluString(),
            'total_voters' => $event->votes()->distinct()->count('visitor_id'),
            'total_votes' => $event->votes()->count(),
            'categories' => $result,
        ];
    }
}
