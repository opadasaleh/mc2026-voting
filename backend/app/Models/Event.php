<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

#[Fillable([
    'slug', 'name', 'description', 'is_active', 'voting_enabled', 'opens_at', 'closes_at',
    'allowed_cidrs', 'venue_wifi_name',
    'otp_ttl_seconds', 'otp_max_attempts', 'otp_resend_cooldown_seconds',
])]
class Event extends Model
{
    // Display tokens for the TV screens belong to the event itself.
    use HasApiTokens;

    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'voting_enabled' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'allowed_cidrs' => 'array',
            'otp_ttl_seconds' => 'integer',
            'otp_max_attempts' => 'integer',
            'otp_resend_cooldown_seconds' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Voting is open when the manual switch is on and now is inside the optional window.
     */
    public function isVotingOpen(?CarbonInterface $now = null): bool
    {
        return $this->votingStatus($now) === 'open';
    }

    /**
     * @return 'open'|'scheduled'|'closed'
     */
    public function votingStatus(?CarbonInterface $now = null): string
    {
        $now ??= now();

        if (! $this->voting_enabled || ($this->closes_at !== null && $now->gte($this->closes_at))) {
            return 'closed';
        }

        if ($this->opens_at !== null && $now->lt($this->opens_at)) {
            return 'scheduled';
        }

        return 'open';
    }

    /**
     * A read-only token for one TV screen of this event: results only, nothing else.
     */
    public function createDisplayToken(string $name, ?CarbonInterface $expiresAt = null): NewAccessToken
    {
        return $this->createToken($name, ['results:read', 'event:'.$this->getKey()], $expiresAt);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Exhibitor, $this>
     */
    public function exhibitors(): HasMany
    {
        return $this->hasMany(Exhibitor::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
