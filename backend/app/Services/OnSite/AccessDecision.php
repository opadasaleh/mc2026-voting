<?php

namespace App\Services\OnSite;

/**
 * The outcome of an on-site check, as returned by GET /events/{event}/access-check.
 */
final readonly class AccessDecision
{
    public const OUTSIDE_IP_RANGE = 'OUTSIDE_IP_RANGE';

    public function __construct(
        public bool $onSite,
        public ?string $reason,
    ) {}

    /**
     * @return array{on_site: bool, reason: ?string}
     */
    public function toArray(): array
    {
        return [
            'on_site' => $this->onSite,
            'reason' => $this->reason,
        ];
    }
}
