<?php

use App\Booking\OpeningHoursSummary;
use App\Models\OpeningHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    OpeningHoursSummary::forgetCachedSchedule();
});

test('the fresh install reads as tuesday to saturday open, sunday and monday closed, in one grouped line', function () {
    expect(OpeningHoursSummary::text())
        ->toBe('Martes a sábado: 9:00–19:00 · Domingo y lunes: cerrado');
});

test('the fresh install exposes one schema.org range and omits the closed days entirely', function () {
    expect(OpeningHoursSummary::schemaSpecifications())->toBe([
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'opens' => '09:00',
            'closes' => '19:00',
        ],
    ]);
});

test('a split schedule joins both ranges of the same day with "y"', function () {
    OpeningHour::query()->delete();
    foreach ([2, 3, 4, 5] as $weekday) {
        OpeningHour::create(['weekday' => $weekday, 'opens_at' => '09:00:00', 'closes_at' => '14:00:00']);
        OpeningHour::create(['weekday' => $weekday, 'opens_at' => '16:00:00', 'closes_at' => '20:00:00']);
    }
    OpeningHour::create(['weekday' => 6, 'opens_at' => '09:00:00', 'closes_at' => '14:00:00']);
    OpeningHoursSummary::forgetCachedSchedule();

    expect(OpeningHoursSummary::text())
        ->toBe('Martes a viernes: 9:00–14:00 y 16:00–20:00 · Sábado: 9:00–14:00 · Domingo y lunes: cerrado');
});

test('a week fully closed reads as one single closed line', function () {
    OpeningHour::query()->delete();
    OpeningHoursSummary::forgetCachedSchedule();

    expect(OpeningHoursSummary::text())->toBe('Lunes a domingo: cerrado');
    expect(OpeningHoursSummary::schemaSpecifications())->toBe([]);
});

test('a week fully open the same hours every day reads as one single line', function () {
    OpeningHour::query()->delete();
    foreach (range(1, 7) as $weekday) {
        OpeningHour::create(['weekday' => $weekday, 'opens_at' => '10:00:00', 'closes_at' => '18:00:00']);
    }
    OpeningHoursSummary::forgetCachedSchedule();

    expect(OpeningHoursSummary::text())->toBe('Lunes a domingo: 10:00–18:00');
});

test('text() and schemaSpecifications() together make a single query per request', function () {
    DB::enableQueryLog();

    OpeningHoursSummary::text();
    OpeningHoursSummary::schemaSpecifications();
    OpeningHoursSummary::text();

    expect(DB::getQueryLog())->toHaveCount(1);

    DB::flushQueryLog();
    DB::disableQueryLog();
});

test('forgetCachedSchedule() makes the next call query again', function () {
    DB::enableQueryLog();

    OpeningHoursSummary::text();
    OpeningHoursSummary::forgetCachedSchedule();
    OpeningHoursSummary::text();

    expect(DB::getQueryLog())->toHaveCount(2);

    DB::flushQueryLog();
    DB::disableQueryLog();
});
