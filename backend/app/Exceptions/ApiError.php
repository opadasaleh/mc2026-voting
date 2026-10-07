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

    /**
     * @param  'closed'|'scheduled'  $status
     */
    public static function votingClosed(string $status): self
    {
        return new self('VOTING_CLOSED', $status === 'scheduled' ? 'Voting has not started yet.' : 'Voting is closed.', 403, ['status' => $status]);
    }

    public static function otpRateLimited(int $retryAfter): self
    {
        return new self('OTP_RATE_LIMITED', 'Please wait before requesting another code.', 429, ['retry_after' => max(1, $retryAfter)]);
    }

    public static function otpInvalid(int $attemptsRemaining): self
    {
        return new self('OTP_INVALID', 'That code is not correct.', 422, ['attempts_remaining' => $attemptsRemaining]);
    }

    public static function otpExpired(): self
    {
        return new self('OTP_EXPIRED', 'This code has expired. Request a new one.', 422);
    }

    /**
     * @param  'missing'|'invalid'|'expired'|'wrong_event'  $reason
     */
    public static function unauthenticated(string $reason): self
    {
        return new self('UNAUTHENTICATED', 'Please verify your phone number for this event.', 401, ['reason' => $reason]);
    }

    public static function unverified(): self
    {
        return new self('UNVERIFIED', 'Please verify your phone number for this event.', 403);
    }

    public function render(): JsonResponse
    {
        $response = response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => (object) $this->details,
            ],
        ], $this->status);

        if (isset($this->details['retry_after'])) {
            $response->headers->set('Retry-After', (string) $this->details['retry_after']);
        }

        return $response;
    }
}
