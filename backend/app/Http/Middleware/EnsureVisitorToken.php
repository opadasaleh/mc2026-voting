<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Visitor;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts only a visitor token issued for the event in the URL. Any failure is
 * 401 UNAUTHENTICATED with a reason, which tells the frontend to run the OTP
 * flow for this event. The verified registration is available as
 * $request->attributes->get('registration').
 */
class EnsureVisitorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            throw ApiError::eventNotFound();
        }

        $plainToken = $request->bearerToken();
        if (blank($plainToken)) {
            throw ApiError::unauthenticated('missing');
        }

        $token = PersonalAccessToken::findToken($plainToken);
        if ($token === null || ! $token->tokenable instanceof Visitor) {
            throw ApiError::unauthenticated('invalid');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            throw ApiError::unauthenticated('expired');
        }

        if (! $token->can('event:'.$event->getKey())) {
            throw ApiError::unauthenticated('wrong_event');
        }

        $registration = EventRegistration::where('event_id', $event->getKey())
            ->where('visitor_id', $token->tokenable_id)
            ->first();

        if (! $registration?->isVerified()) {
            throw ApiError::unverified();
        }

        $visitor = $token->tokenable->withAccessToken($token);
        $request->setUserResolver(fn () => $visitor);
        $request->attributes->set('registration', $registration);

        return $next($request);
    }
}
