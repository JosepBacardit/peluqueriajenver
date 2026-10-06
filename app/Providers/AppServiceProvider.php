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

        // "Mi cuenta"'s password change: by user, not by IP, since it is
        // already authenticated (two salon computers sharing one IP must
        // not throttle each other's own account).
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('password-change:'.$request->user()->id)
            ->response(fn () => back()->withErrors(['current_password' => 'Demasiados intentos. Espera unos minutos y vuelve a probar.'])));
    }

    private static function perMinuteBookingLimit(Request $request): Limit
    {
        return Limit::perMinute(5)
            ->by('minute:'.$request->ip())
            ->response(fn () => back()->withInput()->withErrors(['booking' => __('reservas.messages.too_many_attempts')]));
    }
}
