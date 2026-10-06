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

/*
 * Independent review (.ai/reviews/google-maps-consent.md, finding 1): the
 * dynamically created iframe had no accessible name (WCAG 2.1, 2.4.1), and
 * finding 2: a dynamically created iframe's loading="lazy" has no effect,
 * since it is only ever inserted after the click.
 */
test('the dynamically created map iframe gets an accessible title and drops the dead loading attribute', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain("iframe.title = 'Mapa de ubicación de Peluquería Jenver'")
        ->not->toContain("iframe.loading = 'lazy'");
});
