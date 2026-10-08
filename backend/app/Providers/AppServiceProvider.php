<?php

namespace App\Providers;

use App\Exceptions\ApiError;
use App\Models\Event;
use App\Models\User;
use App\Services\OnSite\VenueAccess;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event as EventBus;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Add the CPF-selected gateway here when it is chosen.
        $this->app->bind(SmsSender::class, fn () => match (config('voting.sms.driver')) {
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unknown SMS_DRIVER ['.config('voting.sms.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only these proxies may set X-Forwarded-For; with none configured, the socket address is used.
        if ($proxies = config('voting.trusted_proxies')) {
            TrustProxies::at($proxies);
        }

        // {event} in API paths is the slug of an active event.
        Route::bind('event', fn (string $slug) => Event::where('slug', $slug)->where('is_active', true)->first()
            ?? throw ApiError::eventNotFound());

        // Admin sign-ins (successful and failed) go to the audit log.
        EventBus::listen(fn (Login $login) => $login->user instanceof User
            ? Audit::record('admin.login', userId: $login->user->getKey())
            : null);
        EventBus::listen(fn (Failed $failed) => Audit::record('admin.login_failed', meta: [
            'username' => $failed->credentials['username'] ?? null,
        ]));

        // Venue visitors share one public IP, so they only get a safety ceiling; everyone else is throttled per IP.
        RateLimiter::for('venue-ip', function (Request $request) {
            $ip = (string) $request->ip();
            $event = $request->route('event');

            // Routes without an event (GET /events): venue IPs of any active event count.
            $events = match (true) {
                $event instanceof Event => collect([$event]),
                $event !== null => Event::where('slug', (string) $event)->get(),
                default => Event::where('is_active', true)->get(),
            };
            $atVenue = $events->contains(fn (Event $candidate) => app(VenueAccess::class)->ipAllowed($candidate, $ip));

            return $atVenue
                ? Limit::perMinute(config('voting.rate_limits.venue_ip_per_minute'))->by('venue:'.$ip)
                : Limit::perMinute(config('voting.rate_limits.offsite_ip_per_minute'))->by('offsite:'.$ip);
        });

        // Per visitor token (the token is not resolved yet when the limiter runs, so key on its hash).
        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(config('voting.rate_limits.votes_per_token_per_minute'))
            ->by('votes:'.hash('sha256', (string) $request->bearerToken())));

        // TV screens: per display token (header or ?token=), plus a per-IP ceiling against token guessing.
        RateLimiter::for('results', function (Request $request) {
            $token = $request->bearerToken() ?? $request->query('token');

            return [
                Limit::perMinute(config('voting.rate_limits.results_per_token_per_minute'))
                    ->by('results:'.hash('sha256', is_string($token) ? $token : '')),
                Limit::perMinute(config('voting.rate_limits.results_per_ip_per_minute'))->by('results-ip:'.$request->ip()),
            ];
        });
    }
}
