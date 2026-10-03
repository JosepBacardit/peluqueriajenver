<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the header booking buttons lead to the booking page on desktop and mobile', function () {
    $html = $this->get('/')->assertOk()->getContent();

    $bookingLinks = substr_count($html, 'href="'.route('reservas').'" class="btn-gold');

    expect($bookingLinks)->toBe(2);
    // The phone number in the top bar is kept.
    expect($html)->toContain('href="tel:+34633912050"');
});

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
