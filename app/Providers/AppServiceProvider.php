<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
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
        // Spanish URLs for resource routes (/admin/servicios/crear).
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        // Customer-typed text (names, notes) must never become Markdown
        // links in the booking emails sent from the salon's domain.
        Markdown::withSecuredEncoding();

        // Public booking and cancellation forms: 5 submissions per minute
        // per connection, answered with a message instead of a bare 429.
        RateLimiter::for('bookings', fn (Request $request) => self::perMinuteBookingLimit($request));

        // New bookings also have a daily cap per connection, so one script
        // cannot fill the agenda.
        RateLimiter::for('booking-submissions', fn (Request $request) => [
            self::perMinuteBookingLimit($request),
            Limit::perDay(10)
                ->by('day:'.$request->ip())
                ->response(fn () => back()->withInput()->withErrors(['booking' => __('reservas.messages.too_many_today')])),
        ]);

        // "Mi cuenta"'s password change is throttled by user, not by IP
        // or via a named limiter here: UpdatePasswordRequest calls
        // RateLimiter directly (same key, "password-change:{id}"), the
        // only way to hit on a wrong current password and clear on
        // success without the throttle: middleware's own hashed cache
        // key getting in the way (review finding L2,
        // .ai/reviews/seeders-account.md).
    }

    private static function perMinuteBookingLimit(Request $request): Limit
    {
        return Limit::perMinute(5)
            ->by('minute:'.$request->ip())
            ->response(fn () => back()->withInput()->withErrors(['booking' => __('reservas.messages.too_many_attempts')]));
    }
}
