<?php

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
