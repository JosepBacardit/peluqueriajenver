<?php

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The appointment URL contains the token that lets anyone see and cancel
 * the appointment, so the page must not load third-party analytics (which
 * report the full page URL) nor publish its own URL in meta tags.
 */
test('the appointment page loads no analytics and never exposes its token in meta tags', function () {
    $appointment = Appointment::factory()->create();

    $html = $this->get(route('cita.show', $appointment->token))->assertOk()->getContent();

    expect($html)
        ->not->toContain('googletagmanager')
        ->not->toContain('gtag(')
        ->not->toContain('ahrefs');

    preg_match_all('/<meta[^>]*>|<link[^>]*>/i', $html, $tags);
    foreach ($tags[0] as $tag) {
        expect($tag)->not->toContain($appointment->token);
    }

    expect($html)->toContain('<meta property="og:url" content="'.route('reservas').'">');
});

/*
 * Since the cookie-consent merge (2026-10-06, PR #7): the tracker code is
 * always present in the markup (CacheHeaders caches this page for days,
 * so the gate has to be client-side JS, see CookieConsentTest), but it
 * only ever runs if cookieConsent === 'accepted' in localStorage — unlike
 * before, "the HTML mentions googletagmanager/ahrefs" alone no longer
 * means tracking is unconditional, so this also pins the consent check
 * right there, not just the tracker ids.
 */
test('marketing pages ship the analytics code, gated on cookie consent', function () {
    $html = $this->get('/')->getContent();

    expect($html)
        ->toContain('googletagmanager')
        ->toContain('ahrefs')
        ->toContain("cookieConsent') === 'accepted'");
});
