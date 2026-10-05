<?php

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
});

/**
 * PRF-102: the month view shows the number of confirmed appointments per
 * day; cancelled appointments do not count (same rule as capacity).
 */
test('the month view shows the number of confirmed appointments per day', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-10 09:00', 'ends_at' => '2030-01-10 09:30']);
    Appointment::factory()->create(['starts_at' => '2030-01-10 11:00', 'ends_at' => '2030-01-10 11:30']);
    Appointment::factory()->create(['starts_at' => '2030-01-15 09:00', 'ends_at' => '2030-01-15 09:30']);
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-15 11:00', 'ends_at' => '2030-01-15 11:30']);

    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->toContain('aria-label="10 de enero, 2 citas"');
    expect($html)->toContain('aria-label="15 de enero, 1 cita"');
});

/**
 * PRF-106: today is distinguished with more than just a color.
 */
test('the month view marks today with aria-current', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->toContain('aria-current="date"');
});

/**
 * PRF-105: a weekday with no opening-hours range, and a day covered by a
 * full closure, are both marked "Cerrado" with text.
 */
test('the month view marks weekdays without opening hours and full closures as closed', function () {
    // 2030-01-06 is a Sunday (no opening_hours row for weekday 7).
    ScheduleBlock::create(['starts_at' => '2030-01-20 00:00', 'ends_at' => '2030-01-21 00:00', 'capacity_reduction' => null]);

    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->toContain('aria-label="6 de enero, cerrado"');
    expect($html)->toContain('aria-label="20 de enero, cerrado"');
});

/**
 * A partial closure (capacity reduction, not a full one) does not mark the
 * day as closed on the month overview.
 */
test('the month view does not mark a partial closure as closed', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-09 00:00', 'ends_at' => '2030-01-09 23:59', 'capacity_reduction' => 1]);

    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->not->toContain('aria-label="9 de enero, cerrado"');
});

/**
 * PRF-103: tapping any day (with or without appointments) opens Día.
 */
test('tapping a day in the month view opens the día view for that date', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->toContain('href="'.e(route('admin.agenda', ['vista' => 'dia', 'fecha' => '2030-01-10'])).'"');
});

/**
 * PRF-104: navigating moves a whole month.
 */
test('the month view navigates to the previous and next month', function () {
    Appointment::factory()->create(['starts_at' => '2030-02-05 10:00', 'ends_at' => '2030-02-05 10:30']);

    $html = $this->get(route('admin.agenda', ['vista' => 'mes', 'fecha' => '2030-02-01']))->assertOk()->getContent();

    expect($html)->toContain('Febrero 2030');
    expect($html)->toContain('aria-label="5 de febrero, 1 cita"');
});

/**
 * PRF-102: one aggregate query for the whole month's occupancy, never one
 * query per day.
 */
test('the month view loads its occupancy with one aggregate query, not one per day', function () {
    foreach ([8, 10, 15, 20, 25] as $dayOfMonth) {
        Appointment::factory()->create([
            'starts_at' => "2030-01-{$dayOfMonth} 10:00", 'ends_at' => "2030-01-{$dayOfMonth} 10:30",
        ]);
    }

    DB::enableQueryLog();
    $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk();
    $queries = DB::getQueryLog();

    $appointmentQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from "appointments"'))->count();

    expect($appointmentQueries)->toBe(1);
});

/**
 * PRF-099 / PRF-107: each day cell meets the 44px touch target and the
 * grid uses accessible roles.
 */
test('the month view grid meets the touch target and uses accessible roles', function () {
    $html = $this->get(route('admin.agenda', ['vista' => 'mes']))->assertOk()->getContent();

    expect($html)->toContain('role="grid"');
    expect($html)->toContain('role="columnheader"');
    expect(substr_count($html, 'min-h-11'))->toBeGreaterThanOrEqual(31);
});
