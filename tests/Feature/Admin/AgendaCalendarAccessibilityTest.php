<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
});

/**
 * PRF-106: today must not be distinguished with color/border alone. In the
 * week view's mobile strip, today (when not the selected day) gets a text
 * label in addition to the border.
 */
test('the week view marks a non-selected today with a text label, not just a border color', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-10']))->assertOk()->getContent();

    // 2030-01-08 is today but 2030-01-10 is selected, so today's pill must
    // carry a visible "hoy" text label on top of its border.
    expect($html)->toContain('>hoy<');
});

/**
 * PRF-106: the week view's desktop grid marks today with a text badge too.
 */
test('the week view desktop grid marks today with a text badge', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('Hoy');
});

/**
 * PRF-107: the week view's desktop grid uses an accessible column-header
 * role for each day heading, matching the month view's grid semantics.
 */
test('the week view desktop grid uses role columnheader for each day', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect(substr_count($html, 'role="columnheader"'))->toBe(7);
});

/**
 * PRF-099: Anterior/Siguiente/Hoy in vista Semana and Mes reuse the same
 * 44px-tall button style already used (and measured) in vista Día.
 */
test('semana and mes navigation buttons reuse the 44px Día button style', function (string $vista) {
    $html = $this->get(route('admin.agenda', ['vista' => $vista]))->assertOk()->getContent();

    expect(substr_count($html, 'class="btn-outline w-11 px-0"'))->toBe(2);
})->with(['semana', 'mes']);
