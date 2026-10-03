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

test('marketing pages keep their analytics', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('googletagmanager')->toContain('ahrefs');
});
