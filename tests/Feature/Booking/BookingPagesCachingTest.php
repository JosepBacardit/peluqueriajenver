<?php

use App\Booking\OpeningHoursSummary;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\OpeningHour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * CacheHeaders gives every public HTML page an ETag and "no-cache"
 * instead of a long max-age (2026-10-06): a schedule or "Reserva online"
 * change must show up without waiting out a stale copy. The booking
 * pages must never be cached at all, not even with revalidation: browsers
 * or proxies would show times already taken and forms with an expired
 * CSRF token.
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

test('the marketing pages are no-cache with an ETag, not a long max-age', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-cache')
        ->not->toContain('max-age=86400')
        ->not->toContain('max-age=604800');
    expect($response->headers->get('ETag'))->not->toBeEmpty();
})->with(['home' => '/', 'contacto' => '/contacto']);

test('revisiting a marketing page with its ETag gets a 304 with no body', function (string $path) {
    $etag = $this->get($path)->assertOk()->headers->get('ETag');

    $response = $this->withHeaders(['If-None-Match' => $etag])->get($path);

    $response->assertStatus(304);
    expect($response->getContent())->toBe('');
})->with(['home' => '/', 'contacto' => '/contacto']);

test('the ETag changes when the page content changes (the opening hours)', function () {
    $etagBefore = $this->get('/contacto')->headers->get('ETag');

    OpeningHour::query()->update(['closes_at' => '20:30:00']);
    OpeningHoursSummary::forgetCachedSchedule();

    $etagAfter = $this->get('/contacto')->headers->get('ETag');

    expect($etagAfter)->not->toBe($etagBefore);
});

test('the ETag changes when the online booking switch changes', function () {
    $etagBefore = $this->get('/')->headers->get('ETag');

    BookingSetting::current()->update(['online_booking_enabled' => false]);

    $etagAfter = $this->get('/')->headers->get('ETag');

    expect($etagAfter)->not->toBe($etagBefore);
});

/**
 * Review finding M1 (.ai/reviews/opening-hours-ux.md, 2026-10-06): a HEAD
 * request to a public page used to fall through to the "no-store, no
 * ETag" branch meant for the API/admin, since Laravel keeps
 * $request->getMethod() as "HEAD" even though it routes it like a GET.
 * NGINX-CACHE-CONFIG.md's own verification command is `curl -I` — a HEAD
 * request — so this must behave exactly like GET on the same page.
 */
test('a HEAD request gets the same no-cache + ETag as the equivalent GET (M1)', function (string $path) {
    $get = $this->get($path)->assertOk();
    $head = $this->head($path)->assertOk();

    expect($head->headers->get('Cache-Control'))->toBe($get->headers->get('Cache-Control'));
    expect($head->headers->get('ETag'))->toBe($get->headers->get('ETag'));
    expect($head->headers->get('ETag'))->not->toBeEmpty();
    // Still a real HEAD response: no body, per RFC 2616 §14.13.
    expect($head->getContent())->toBe('');
})->with(['home' => '/', 'contacto' => '/contacto']);

test('HEAD /reservas keeps the no-store policy, not the public ETag branch', function () {
    $response = $this->head('/reservas');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('ETag'))->toBeNull();
});

/**
 * Review finding L1: the ETag scheme only ever answers 304 because none
 * of these pages render a CSRF token, old() input or a session-dependent
 * notice. Two different protections for the same assumption: the
 * rendered HTML never carries the literal markers a form or a flash
 * message would leave, and, separately, requesting the same page twice
 * in the same session produces the exact same ETag (if either stopped
 * being true, this would start failing instead of just quietly serving
 * 200 instead of 304 on every single visit).
 */
test('no public cached page embeds a CSRF token or a session id (L1)', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    expect($html)->not->toContain('name="_token"');
    expect($html)->not->toContain('csrf-token');
})->with([
    'home' => '/',
    'contacto' => '/contacto',
    'color-mechas' => '/color-y-mechas',
    'corte-tratamientos' => '/corte-y-tratamientos',
    'peinados-eventos' => '/peinados-eventos',
    'belleza-estetica' => '/belleza-estetica',
]);

test('the same session gets the exact same ETag on repeated visits (L1)', function (string $path) {
    $first = $this->get($path)->assertOk()->headers->get('ETag');
    $second = $this->get($path)->assertOk()->headers->get('ETag');
    $third = $this->get($path)->assertOk()->headers->get('ETag');

    expect($first)->not->toBeEmpty();
    expect($second)->toBe($first);
    expect($third)->toBe($first);
})->with(['home' => '/', 'contacto' => '/contacto']);

test('a 404 page gets no ETag and no special treatment', function () {
    $response = $this->get('/esta-pagina-no-existe');

    $response->assertNotFound();
    expect($response->headers->get('ETag'))->toBeNull();
});

test('/admin, /cita, /reservas and /api keep their own no-cache policy, untouched', function (string $path) {
    $response = $this->get($path);

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store')->not->toContain('public');
    expect($response->headers->get('ETag'))->toBeNull();
})->with([
    'admin (redirects to login)' => '/admin',
    'reservas' => '/reservas',
]);
