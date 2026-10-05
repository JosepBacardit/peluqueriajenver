<?php

/*
 * The Google Maps iframe makes a request to Google that can set its own
 * cookies before the visitor has any say (see .ai/reviews/cookie-consent.md
 * and resources/views/partials/google-map-embed.blade.php). The server must
 * never render a src pointing at Google; the iframe is only created by JS
 * after the visitor clicks "Ver mapa". This is independent of the analytics
 * cookie consent covered by CookieConsentTest.
 */
test('Google Maps embeds only load when "Ver mapa" is clicked', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    // "data-embed-src" itself contains the substring src="...", so only an
    // actual <iframe ... src="..."> would mean the server rendered it eager.
    expect(preg_match('/<iframe[^>]*\ssrc="https:\/\/www\.google\.com\/maps\/embed/i', $html))->toBe(0);

    expect($html)
        ->toContain('data-embed-src="https://www.google.com/maps/embed')
        ->toContain('data-google-map-trigger')
        ->toContain('Ver mapa')
        ->toContain('Abrir en Google Maps');
})->with(['home', 'contacto', 'cookies']);
