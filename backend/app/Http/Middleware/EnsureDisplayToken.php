<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts only a display token (TV screen) created for the event in the URL.
 * The token comes from the Authorization header or, for the browser's
 * EventSource which cannot set headers, from ?token=. Visitor tokens are
 * rejected. The token is available as $request->attributes->get('display_token').
 */
class EnsureDisplayToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        if (! $event instanceof Event) {
            throw ApiError::eventNotFound();
        }

        $plainToken = $request->bearerToken() ?? $request->query('token');
        if (blank($plainToken) || ! is_string($plainToken)) {
            throw ApiError::displayUnauthenticated('missing');
        }

        $token = PersonalAccessToken::findToken($plainToken);
        if ($token === null || ! $token->tokenable instanceof Event || ! $token->can('results:read')) {
            throw ApiError::displayUnauthenticated('invalid');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            throw ApiError::displayUnauthenticated('expired');
        }

        if (! $token->tokenable->is($event) || ! $token->can('event:'.$event->getKey())) {
            throw ApiError::displayUnauthenticated('wrong_event');
        }

        // Lets admins see which screens are connected, without a write on every poll.
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('display_token', $token);

        return $next($request);
    }
}
