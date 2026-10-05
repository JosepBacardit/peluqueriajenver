<?php

use App\Booking\DayTimeline;
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
