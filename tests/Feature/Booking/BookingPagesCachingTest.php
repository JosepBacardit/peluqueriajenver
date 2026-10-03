<?php

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * CacheHeaders marks every public HTML page as cacheable for days. The
 * booking pages must never be cached: browsers or proxies would show
 * times already taken and forms with an expired CSRF token.
 */
test('booking pages are never publicly cached', function (string $path) {
    $cacheControl = $this->get($path)->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')->toContain('private')->not->toContain('public');
})->with([
    'booking page' => '/reservas',
    'booking page with a service' => '/reservas?servicio=1',
]);

test('the appointment page is never publicly cached', function () {
    $appointment = Appointment::factory()->create();

    $cacheControl = $this->get(route('cita.show', $appointment->token))->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')->not->toContain('public');
});

test('the marketing pages keep their public cache', function () {
    expect($this->get('/')->headers->get('Cache-Control'))->toContain('public');
    expect($this->get('/contacto')->headers->get('Cache-Control'))->toContain('public');
});
