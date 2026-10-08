<?php

namespace App\Services\Otp;

use App\Exceptions\ApiError;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\OtpCode;
use App\Models\Visitor;
use App\Services\Sms\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-event phone verification (F5, F6): a visitor registers with full name + phone,
 * receives a 6-digit SMS code, and gets an API token bound to this event only.
 */
class OtpService
{
    public function __construct(private readonly SmsSender $sms) {}

    /**
     * @param  string  $phone  E.164 (already normalised)
     * @return array{expires_in: int, resend_after: int}
     */
    public function request(Event $event, string $fullName, string $phone, ?string $ip): array
    {
        if (! $event->isVotingOpen()) {
            throw ApiError::votingClosed($event->votingStatus());
        }

        $phoneHash = PhoneNumber::hash($phone);

        // SMS cost: limited per phone across all events.
        $limitKey = 'otp-request:'.$phoneHash;
        if (RateLimiter::tooManyAttempts($limitKey, config('voting.otp.requests_per_phone_per_15_minutes'))) {
            throw ApiError::otpRateLimited(RateLimiter::availableIn($limitKey));
        }

        $visitor = Visitor::createOrFirst(
            ['phone_hash' => $phoneHash],
            ['full_name' => $fullName, 'phone_encrypted' => $phone],
        );

        $registration = EventRegistration::createOrFirst(
            ['event_id' => $event->getKey(), 'visitor_id' => $visitor->getKey()],
            ['full_name' => $fullName, 'registered_ip' => $ip],
        );

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($event, $registration, $fullName, $code, $ip): void {
            // Serialises concurrent requests for the same registration.
            $registration = EventRegistration::whereKey($registration->getKey())->lockForUpdate()->sole();

            $latest = $registration->otpCodes()->latest('created_at')->first();
            if ($latest !== null) {
                $wait = $event->otp_resend_cooldown_seconds - (int) $latest->created_at->diffInSeconds(now(), true);
                if ($wait > 0) {
                    throw ApiError::otpRateLimited($wait);
                }
            }

            // Only the visitor's verified name is kept; nobody can rename someone else by requesting a code.
            if (! $registration->isVerified()) {
                $registration->update(['full_name' => $fullName]);
            }

            $registration->otpCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);
            $registration->otpCodes()->create([
                'code_hash' => $this->hashCode($registration, $code),
                'expires_at' => now()->addSeconds($event->otp_ttl_seconds),
                'ip' => $ip,
            ]);
        });

        RateLimiter::hit($limitKey, 15 * 60);

        $minutes = (int) ceil($event->otp_ttl_seconds / 60);
        $this->sms->send($phone, "Your {$event->name} voting code is {$code}. It expires in {$minutes} minutes.");

        return ['expires_in' => $event->otp_ttl_seconds, 'resend_after' => $event->otp_resend_cooldown_seconds];
    }

    /**
     * @param  string  $phone  E.164 (already normalised)
     * @return array{token: string, token_type: string, expires_at: string, visitor: array{full_name: string}}
     */
    public function verify(Event $event, string $phone, string $code, ?string $ip): array
    {
        $phoneHash = PhoneNumber::hash($phone);

        $limitKey = 'otp-verify:'.$phoneHash;
        if (RateLimiter::tooManyAttempts($limitKey, config('voting.otp.verifications_per_phone_per_minute'))) {
            throw ApiError::otpRateLimited(RateLimiter::availableIn($limitKey));
        }
        RateLimiter::hit($limitKey, 60);

        $registration = EventRegistration::where('event_id', $event->getKey())
            ->whereHas('visitor', fn ($query) => $query->where('phone_hash', $phoneHash))
            ->first();

        if ($registration === null) {
            throw ApiError::otpExpired();
        }

        // Decided inside a locked transaction; errors are thrown after commit so a failed attempt is still counted.
        [$outcome, $remaining] = DB::transaction(function () use ($event, $registration, $code): array {
            $otp = OtpCode::where('event_registration_id', $registration->getKey())
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->where('attempts', '<', $event->otp_max_attempts)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($otp === null) {
                return ['expired', 0];
            }

            if (! hash_equals($otp->code_hash, $this->hashCode($registration, $code))) {
                $otp->increment('attempts');

                return ['invalid', $event->otp_max_attempts - $otp->attempts];
            }

            $otp->update(['consumed_at' => now()]);

            if (! $registration->isVerified()) {
                $registration->update(['phone_verified_at' => now()]);
            }
            $registration->visitor->update(['full_name' => $registration->full_name]);

            return ['verified', 0];
        });

        if ($outcome === 'expired') {
            throw ApiError::otpExpired();
        }

        if ($outcome === 'invalid') {
            throw $remaining > 0 ? ApiError::otpInvalid($remaining) : ApiError::otpExpired();
        }

        RateLimiter::clear($limitKey);

        return $this->issueToken($event, $registration->visitor);
    }

    /**
     * @return array{token: string, token_type: string, expires_at: string, visitor: array{full_name: string}}
     */
    private function issueToken(Event $event, Visitor $visitor): array
    {
        $expiresAt = now()->addHours(config('voting.visitor_token_hours'));
        if ($event->closes_at !== null && $event->closes_at->lt($expiresAt)) {
            $expiresAt = $event->closes_at;
        }

        $token = $visitor->createToken('visitor:'.$event->slug, ['vote', 'event:'.$event->getKey()], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601ZuluString(),
            'visitor' => ['full_name' => $visitor->full_name],
        ];
    }

    private function hashCode(EventRegistration $registration, string $code): string
    {
        return hash_hmac('sha256', $registration->getKey().'|'.$code, config('app.key'));
    }
}
