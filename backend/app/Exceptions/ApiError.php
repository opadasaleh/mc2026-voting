<?php

namespace App\Exceptions;

use App\Services\OnSite\AccessDecision;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An error in the API contract's shape: { "error": { "code", "message", "details" } }.
 * The message is safe to show to visitors; clients branch on the code.
 */
class ApiError extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function eventNotFound(): self
    {
        return new self('EVENT_NOT_FOUND', 'This event does not exist or is no longer available.', 404);
    }

    public static function notOnSite(AccessDecision $decision, ?string $venueWifiName): self
    {
        $details = ['reason' => $decision->reason, 'venue_wifi_name' => $venueWifiName];

        if ($decision->reason === AccessDecision::LOCATION_REQUIRED) {
            return new self('LOCATION_REQUIRED', 'Allow location access to vote at this event.', 403, $details);
        }

        return new self('OFF_SITE', 'Voting is only available on the event Wi-Fi.', 403, $details);
    }

    /**
     * @param  array<string, list<string>>  $fields
     */
    public static function validationFailed(array $fields): self
    {
        return new self('VALIDATION_FAILED', 'Some fields are invalid.', 422, ['fields' => $fields]);
    }

    public static function tooManyRequests(int $retryAfter): self
    {
        return new self('TOO_MANY_REQUESTS', 'Too many requests. Please wait a moment and try again.', 429, ['retry_after' => $retryAfter]);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => (object) $this->details,
            ],
        ], $this->status);
    }
}
