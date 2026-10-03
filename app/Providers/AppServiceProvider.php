<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
        // Spanish URLs for resource routes (/admin/servicios/crear).
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        // Public booking and cancellation forms: 5 submissions per minute
        // per connection, answered with a message instead of a bare 429.
        RateLimiter::for('bookings', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip())
            ->response(fn () => back()->withInput()->withErrors(['booking' => __('reservas.messages.too_many_attempts')])));
    }
}
