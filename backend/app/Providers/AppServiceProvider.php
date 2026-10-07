<?php

namespace App\Providers;

use App\Exceptions\ApiError;
use App\Models\Event;
use App\Services\OnSite\VenueAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        // Venue visitors share one public IP, so they only get a safety ceiling; everyone else is throttled per IP.
        RateLimiter::for('venue-ip', function (Request $request) {
            $event = $request->route('event');
            $event = $event instanceof Event ? $event : Event::where('slug', (string) $event)->first();
            $ip = (string) $request->ip();

            return $event && app(VenueAccess::class)->ipAllowed($event, $ip)
                ? Limit::perMinute(config('voting.rate_limits.venue_ip_per_minute'))->by('venue:'.$ip)
                : Limit::perMinute(config('voting.rate_limits.offsite_ip_per_minute'))->by('offsite:'.$ip);
        });
    }
}
