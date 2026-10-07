<?php

namespace App\Services\OnSite;

/**
 * The outcome of an on-site check, as returned by GET /events/{event}/access-check.
 */
final readonly class AccessDecision
{
    public const OUTSIDE_IP_RANGE = 'OUTSIDE_IP_RANGE';

    public const OUTSIDE_GEOFENCE = 'OUTSIDE_GEOFENCE';

    public const LOCATION_REQUIRED = 'LOCATION_REQUIRED';

    public function __construct(
        public bool $onSite,
        public string $mode,
        public bool $requiresLocation,
        public ?string $reason,
    ) {}

    /**
     * @return array{on_site: bool, mode: string, requires_location: bool, reason: ?string}
     */
    public function toArray(): array
    {
        return [
            'on_site' => $this->onSite,
            'mode' => $this->mode,
            'requires_location' => $this->requiresLocation,
            'reason' => $this->reason,
        ];
    }
}
