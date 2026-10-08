<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['event_id', 'slug', 'name', 'description', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // An event has at most voting.max_categories_per_event award categories (3 for MC2026).
        static::creating(function (Category $category): void {
            if (static::where('event_id', $category->event_id)->count() >= static::maxPerEvent()) {
                throw new LogicException('An event can have at most '.static::maxPerEvent().' categories.');
            }
        });
    }

    public static function maxPerEvent(): int
    {
        return (int) config('voting.max_categories_per_event');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<Exhibitor, $this>
     */
    public function exhibitors(): HasMany
    {
        return $this->hasMany(Exhibitor::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
