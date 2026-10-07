<?php

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Merged in from main (2026-10-06, PR #8): every page using
// layouts/app.blade.php now reads the schedule (footer.blade.php,
// OpeningHoursSummary) and the online-booking switch from the database,
// so even this file's unrelated tests need a migrated connection.

/*
 * GTM, GA4 and Ahrefs must not run until the visitor accepts cookies in the
 * banner (AEPD/RGPD: analytics cookies need prior, explicit consent). Pages
 * are cached for days (see CacheHeaders), so the same HTML is served to
 * every visitor: the gate has to live in client-side JS reading
 * localStorage, not in server-rendered markup. Pest cannot execute that JS,
 * so these tests check the structure of the HTML it is given: the tracker
 * loading code must sit behind the consent check, and the GTM <noscript>
 * beacon (which cannot be gated at all) must be gone.
 */

test('GTM, GA4 and Ahrefs only load behind a stored cookie consent', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    foreach (['GTM-NP6KXF9K', 'G-EX4HPXH0WV', 'analytics.ahrefs.com'] as $trackerId) {
        $scripts = preg_split('/(?=<script)/i', $html);
        $script = collect($scripts)->first(fn ($block) => str_contains($block, $trackerId));

        expect($script)->not->toBeNull();
        expect($script)->toContain("cookieConsent') === 'accepted'");
    }
});

test('the Google Tag Manager noscript beacon is removed because it cannot be gated by consent', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('googletagmanager.com/ns.html');
});

test('the footer and the cookies policy page offer a way to reopen the consent banner', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    expect($home)->toContain('data-cookie-settings');

    $cookiesPage = $this->get(route('cookies'))->assertOk()->getContent();
    expect($cookiesPage)->toContain('data-cookie-settings');
});

test('the cookies policy no longer claims that browsing the site implies consent', function () {
    $html = $this->get(route('cookies'))->assertOk()->getContent();

    expect($html)->not->toContain('Al utilizar este sitio web, aceptas el uso de cookies');
});

/*
 * Independent review (.ai/reviews/cookie-consent.md, finding 1): the GTM
 * container only has the GA4 tag (confirmed by the user), so there is no
 * "advertising" cookie category, and Ahrefs — which sets no cookie at all —
 * must never be described as "installing" one.
 */
test('the cookies policy drops the advertising category and never claims Ahrefs installs a cookie', function () {
    $html = $this->get(route('cookies'))->assertOk()->getContent();

    expect($html)
        ->not->toContain('Cookies de Publicidad')
        ->not->toContain('publicidad (Google Tag Manager')
        ->toContain('Ahrefs')
        ->toContain('no instala')
        ->not->toContain('Ahrefs Analytics) solo se instalan');
});

test('the cookie banner no longer mentions an advertising cookie category', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('análisis y publicidad');
});

/*
 * Finding 2: gtag.js defaults to cookie_domain "auto", which sets _ga on
 * the root domain, not on location.hostname (e.g. www.example.com). The
 * cleanup must derive the root domain instead of hardcoding it, so it also
 * works on localhost in local development.
 */
test('clearing analytics cookies computes the root domain instead of hardcoding it', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    $start = strpos($html, '// Cookie Banner Logic');
    expect($start)->not->toBeFalse();
    $bannerScript = substr($html, $start);

    expect($bannerScript)
        ->toContain('replace(/^www\.')
        ->not->toContain('peluqueriajenver.com');
});

/*
 * Finding 3: some browsers block localStorage entirely. Every access must
 * be guarded so a throw never leaves the banner stuck and never loads a
 * tracker without confirmed consent.
 */
test('every localStorage access in the consent banner and layout is guarded with try/catch', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, 'try {'))->toBeGreaterThanOrEqual(4);
    expect(substr_count($html, 'catch (e)'))->toBeGreaterThanOrEqual(4);
});

/*
 * Merging T013's H1 (.ai/reviews/reservas.md: the appointment page's URL
 * carries its own token, so it must never reach any analytics vendor)
 * with M4 (this file: analytics is consent-gated everywhere else).
 * /cita/{token} sets @section('without_analytics'), which now wraps both
 * the GTM head script and the deferred GA4/Ahrefs script in
 * layouts/app.blade.php — neither ever registers a loader there, gated
 * on consent or not, so there is nothing a stray "Aceptar" elsewhere
 * could ever trigger on that page.
 */
test('the appointment page registers no analytics consent loader at all', function () {
    $appointment = Appointment::factory()->create();

    $html = $this->get(route('cita.show', $appointment->token))->assertOk()->getContent();

    // cookie-banner.blade.php (included on every page) still mentions
    // __analyticsConsentLoaders in its own "Aceptar" handler, reading an
    // empty array here harmlessly; what must never happen on this page
    // is something actually registering a loader onto it.
    expect($html)->not->toContain('__analyticsConsentLoaders.push');
});

test('the home page registers the GTM and the deferred GA4/Ahrefs loaders, both gated on consent', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, '__analyticsConsentLoaders.push'))->toBe(2);
    expect(substr_count($html, "cookieConsent') === 'accepted'"))->toBe(2);
});
