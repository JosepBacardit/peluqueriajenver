<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the header and hero booking buttons lead to the booking page on the home page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    $bookingLinks = substr_count($html, 'href="'.route('reservas').'" class="btn-gold');

    // Header CTA (desktop + mobile), the hero "Reservar cita" button and
    // the "Reservar online" button of the "Reserva tu cita" section.
    expect($bookingLinks)->toBe(4);
    // The phone number in the top bar, and the other call-to-call CTAs
    // further down the page (free diagnosis, "Llamar ahora"), are kept.
    expect($html)->toContain('href="tel:+34633912050"');
});

test('the "Reservar cita" button on each service page leads to the booking page, while its "Llamar" button stays on the phone', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    // Scoped to the hero CTA's own classes, not the header's "Reservar
    // cita" button (also present on every page via the layout).
    $reserveHero = preg_quote('<a href="'.route('reservas').'" class="btn-gold text-sm md:text-base">', '#');
    expect($html)->toMatch('#'.$reserveHero.'\s*Reservar cita#');
    // The lower "Llamar" call-to-action is a different CTA and keeps calling.
    expect($html)->toContain('href="tel:+34633912050"');
})->with([
    'color-mechas',
    'corte-tratamientos',
    'peinados-eventos',
    'belleza-estetica',
]);

test('the booking page is indexable and in the sitemap', function () {
    $this->get(route('reservas'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('reservas').'">', false);

    $sitemap = simplexml_load_string($this->get('/sitemap.xml')->getContent());
    $locations = [];
    foreach ($sitemap->url as $url) {
        $locations[] = (string) $url->loc;
    }

    expect($locations)->toContain(route('reservas'));
});

test('the structured data declares the booking page as the reserve action and keeps the phone', function () {
    $html = $this->get('/')->getContent();

    preg_match_all('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $matches);
    $salon = collect($matches[1])
        ->map(fn (string $json) => json_decode($json, true))
        ->first(fn (?array $data) => is_array($data) && in_array('HairSalon', (array) ($data['@type'] ?? [])));

    expect($salon['potentialAction']['@type'])->toBe('ReserveAction');
    expect($salon['potentialAction']['target']['urlTemplate'])->toBe(route('reservas'));
    expect($salon['telephone'])->toBe('+34633912050');
    expect($salon['contactPoint']['telephone'])->toBe('+34633912050');
});

test('the "Reserva tu cita" section of the home page offers online booking and keeps the phone and WhatsApp', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#<section id="reserva".*?</section>#s', $html, $section);

    expect($section)->not->toBeEmpty();
    expect($section[0])->toMatch('#<a href="'.preg_quote(route('reservas'), '#').'"[^>]*>\s*Reservar online\s*</a>#');
    expect($section[0])->toContain('href="tel:+34633912050"');
    expect($section[0])->toContain('href="https://wa.me/34633912050');
});

test('the questions about booking mention online booking without promising it for every service', function () {
    $home = $this->get('/')->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">\s*(\{"@context":"https://schema.org","@type":"FAQPage".*?)\s*</script>#s', $home, $faqSchema);
    $questions = collect(json_decode($faqSchema[1], true)['mainEntity'])->pluck('acceptedAnswer.text', 'name');

    expect($questions['¿Puedo pedir cita online?'])->toContain('página de reservas')->toContain('WhatsApp');

    $this->get(route('belleza-estetica'))->assertOk()
        ->assertSee('Puedes reservar online los servicios que aparecen en nuestra página de reservas, o llamarnos o escribirnos por WhatsApp.');
});
