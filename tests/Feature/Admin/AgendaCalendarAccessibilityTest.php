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

/**
 * Review finding M3, revisited for T039: the desktop week grid is no
 * longer a tabular list-per-day (where role="grid"/"row"/"gridcell" fit),
 * but an hourly timeline like Día's — content a screen reader reads in
 * chronological DOM order via real links, same as Día. Its header row
 * (day names) is still genuinely tabular, so it keeps role="row" and
 * "columnheader"; the mobile day strip (still a one-row grid of day
 * pills) keeps role="row"/"gridcell" as before. A full accessibility pass
 * over the whole grid is T040's job.
 */
test('the week view groups its cells in role rows and gridcells', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    // 1 row for the mobile 7-day strip + 1 row for the desktop header.
    expect(substr_count($html, 'role="row"'))->toBe(2);
    // 7 gridcells in the mobile strip only (the desktop timeline body is
    // no longer gridcell-based).
    expect(substr_count($html, 'role="gridcell"'))->toBe(7);
});

/**
 * Review finding M3: same completion for vista Mes.
 */
test('the month view groups its cells in role rows and gridcells', function () {
    // January 2030: 1 leading blank, 31 days chunked into 5 weeks — 1
    // header row + 5 week rows, 31 gridcells, 1 presentation blank.
    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect(substr_count($html, 'role="row"'))->toBe(6);
    expect(substr_count($html, 'role="gridcell"'))->toBe(31);
    expect(substr_count($html, 'role="presentation"'))->toBe(1);
});

/**
 * Review finding L3: when the shown week crosses from one month into the
 * next, the desktop grid's column headers show "d/m" for the days already
 * in the new month, not just the day number.
 */
test('the week view desktop grid shows the month once the week crosses into it', function () {
    // The week of 2030-01-28 (Monday) to 2030-02-03 (Sunday) crosses from
    // January into February on Friday the 1st.
    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-30']))->assertOk()->getContent();

    expect($html)->toContain('lunes 28');
    expect($html)->toContain('viernes 01/02');
});

/**
 * Review finding N1: the period label only capitalizes its first letter.
 * A blanket CSS "capitalize" class used to title-case every word, turning
 * "Semana del ... al ..." into "Semana Del ... Al ...".
 */
test('día\'s label capitalizes only the weekday\'s first letter', function () {
    $this->get(route('admin.agenda'))->assertOk()->assertSee('Martes 08/01/2030');
});

test('semana\'s label does not capitalize "del"/"al"', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'semana']))->assertOk()->getContent();

    expect($html)->toContain('Semana del 07/01 al 13/01/2030');
    expect($html)->not->toContain('Semana Del');
    expect($html)->not->toContain(' Al ');
});

test('mes\'s label capitalizes only the month\'s first letter', function () {
    $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->assertSee('Enero 2030');
});

/**
 * Review finding N2: the view switcher, the Anterior/Siguiente/Hoy
 * controls and the date form sit in one row from md up, instead of three
 * stacked blocks that wasted the extra width desktop has.
 */
test('the agenda nav lays out in a single row from md up', function () {
    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('md:flex-row md:items-center md:justify-between');
});
