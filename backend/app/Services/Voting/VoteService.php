<?php

namespace App\Services\Voting;

use App\Exceptions\ApiError;
use App\Models\Category;
use App\Models\Event;
use App\Models\Exhibitor;
use App\Models\Visitor;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Casts one vote in one category (F2, F12). The database guarantees one vote per
 * visitor per category, so this is safe under concurrency and retries.
 */
class VoteService
{
    private const UNIQUE_VIOLATION = '23505';

    /**
     * @return array{vote: Vote, created: bool}
     */
    public function cast(Event $event, Visitor $visitor, int $categoryId, int $exhibitorId, ?string $ip): array
    {
        if (! $event->isVotingOpen()) {
            throw ApiError::votingClosed($event->votingStatus());
        }

        $category = Category::where('event_id', $event->getKey())->where('is_active', true)->find($categoryId)
            ?? throw ValidationException::withMessages(['category_id' => 'Unknown category.']);

        $exhibitorInCategory = Exhibitor::where('category_id', $category->getKey())
            ->where('is_active', true)
            ->whereKey($exhibitorId)
            ->exists();

        if (! $exhibitorInCategory) {
            throw ApiError::invalidExhibitorForCategory();
        }

        try {
            // Own transaction (a savepoint inside an outer one): a unique violation must not abort the caller's transaction.
            $vote = DB::transaction(fn () => Vote::create([
                'event_id' => $event->getKey(),
                'visitor_id' => $visitor->getKey(),
                'category_id' => $category->getKey(),
                'exhibitor_id' => $exhibitorId,
                'ip' => $ip,
            ]));

            return ['vote' => $vote, 'created' => true];
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }
        }

        // Already voted in this category: the same choice is a harmless retry, a different one is refused.
        $existing = Vote::where('visitor_id', $visitor->getKey())->where('category_id', $category->getKey())->sole();

        if ($existing->exhibitor_id !== $exhibitorId) {
            throw ApiError::alreadyVoted($existing->category_id, $existing->exhibitor_id);
        }

        return ['vote' => $existing, 'created' => false];
    }

    /**
     * @return list<int>
     */
    public function remainingCategoryIds(Event $event, Visitor $visitor): array
    {
        return $event->categories()
            ->where('is_active', true)
            ->whereNotIn('id', Vote::where('event_id', $event->getKey())->where('visitor_id', $visitor->getKey())->select('category_id'))
            ->pluck('id')
            ->all();
    }
}
