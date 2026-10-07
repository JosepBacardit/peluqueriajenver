<?php

use App\Booking\DayTimeline;
use App\Models\Appointment;
use App\Models\OpeningHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * PRF-108: the grid spans the earliest opening to the latest closing of
 * the whole week, rounded out to the hour, so every day/column shares the
 * same vertical scale.
 */
test('weekBounds spans the earliest opening to the latest closing of the week, in minutes since midnight', function () {
    // Default schedule: Tuesday-Saturday 09:00-19:00.
    $bounds = DayTimeline::weekBounds(OpeningHour::all());

    expect($bounds)->toBe(['start' => 9 * 60, 'end' => 19 * 60]);
});

test('weekBounds rounds out to the hour when opening times are not already on the hour', function () {
    OpeningHour::query()->delete();
    OpeningHour::create(['weekday' => 2, 'opens_at' => '08:30:00', 'closes_at' => '19:15:00']);

    $bounds = DayTimeline::weekBounds(OpeningHour::all());

    expect($bounds)->toBe(['start' => 8 * 60, 'end' => 20 * 60]);
});

test('weekBounds falls back to a default range when there is no opening-hours data at all', function () {
    OpeningHour::query()->delete();

    $bounds = DayTimeline::weekBounds(OpeningHour::all());

    expect($bounds)->toBe(['start' => 9 * 60, 'end' => 19 * 60]);
});

/**
 * PRF-108 / PRF-115: 88px/hour (44px per half hour), the scale the salon's
 * phone touch target needs.
 */
test('pxFromMinutes converts minutes since the grid start into pixels at 88px/hour, without drifting', function () {
    expect(DayTimeline::pxFromMinutes(0))->toBe(0);
    expect(DayTimeline::pxFromMinutes(30))->toBe(44);
    expect(DayTimeline::pxFromMinutes(60))->toBe(88);
    expect(DayTimeline::pxFromMinutes(90))->toBe(132);
    // A duration's height must be the difference of two rounded positions
    // (never round(duration) on its own), so adjacent segments never gap
    // or overlap by a rounding pixel.
    expect(DayTimeline::pxFromMinutes(615) - DayTimeline::pxFromMinutes(600))->toBe(22);
});

/**
 * PRF-116: the "now" line only shows for today, and only when "now" falls
 * inside the grid's range.
 */
test('nowLineTop gives the pixel offset for today, within the grid range', function () {
    $now = CarbonImmutable::parse('2030-01-08 11:15'); // 11:15, grid 09:00-19:00
    $day = CarbonImmutable::parse('2030-01-08')->startOfDay();

    expect(DayTimeline::nowLineTop($day, 9 * 60, 19 * 60, $now))->toBe(DayTimeline::pxFromMinutes(135));
});

test('nowLineTop is null when the shown day is not today', function () {
    $now = CarbonImmutable::parse('2030-01-08 11:15');
    $otherDay = CarbonImmutable::parse('2030-01-09')->startOfDay();

    expect(DayTimeline::nowLineTop($otherDay, 9 * 60, 19 * 60, $now))->toBeNull();
});

test('nowLineTop is null when "now" falls outside the grid range', function (string $time) {
    $now = CarbonImmutable::parse("2030-01-08 {$time}");
    $day = CarbonImmutable::parse('2030-01-08')->startOfDay();

    expect(DayTimeline::nowLineTop($day, 9 * 60, 19 * 60, $now))->toBeNull();
})->with(['07:00', '19:00', '23:59']);

/**
 * Review finding H1: extends the grid to cover a confirmed appointment
 * forced outside the normal opening hours ("Guardar igualmente"), rounded
 * out to the hour like weekBounds().
 */
test('extendBounds grows the grid end to cover a confirmed appointment outside the normal range', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 19:30', 'ends_at' => '2030-01-08 20:05']);

    $bounds = DayTimeline::extendBounds(9 * 60, 19 * 60, collect([$appointment]));

    expect($bounds)->toBe(['start' => 9 * 60, 'end' => 21 * 60]); // 20:05 rounds out to 21:00
});

test('extendBounds also grows the start when an appointment starts before the normal opening', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 07:40', 'ends_at' => '2030-01-08 08:30']);

    $bounds = DayTimeline::extendBounds(9 * 60, 19 * 60, collect([$appointment]));

    expect($bounds)->toBe(['start' => 7 * 60, 'end' => 19 * 60]);
});

test('extendBounds ignores a cancelled appointment outside the range', function () {
    $appointment = Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 20:00', 'ends_at' => '2030-01-08 20:30']);

    $bounds = DayTimeline::extendBounds(9 * 60, 19 * 60, collect([$appointment]));

    expect($bounds)->toBe(['start' => 9 * 60, 'end' => 19 * 60]);
});

test('extendBounds leaves the grid unchanged when every appointment already fits', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);

    $bounds = DayTimeline::extendBounds(9 * 60, 19 * 60, collect([$appointment]));

    expect($bounds)->toBe(['start' => 9 * 60, 'end' => 19 * 60]);
});
