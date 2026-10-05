<?php

use App\Booking\DayTimeline;
use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * Builds a day's timeline with the default fixture schedule (09:00-19:00,
 * one tramo) unless $ranges/$blocks/$appointments override it. $now
 * defaults to the day before (00:00), so every slot in $date counts as
 * "future" (review finding L2) unless a test overrides it on purpose.
 */
function buildDay(
    string $date = '2030-01-08',
    ?Collection $ranges = null,
    ?Collection $appointments = null,
    ?Collection $blocks = null,
    int $capacity = 2,
    int $gridStart = 540,
    int $gridEnd = 1140,
    ?CarbonImmutable $now = null,
): array {
    $day = CarbonImmutable::parse($date)->startOfDay();

    return DayTimeline::build(
        $day,
        $gridStart,
        $gridEnd,
        $capacity,
        $ranges ?? collect([OpeningHour::make(['weekday' => $day->isoWeekday(), 'opens_at' => '09:00:00', 'closes_at' => '19:00:00'])]),
        $appointments ?? collect(),
        $blocks ?? collect(),
        $now ?? $day->subDay(),
    );
}

/**
 * @return list<array> the given $lane's segments, in order
 */
function laneSegments(array $piece, int $lane): array
{
    return collect($piece['segments'])->filter(fn (array $s) => $s['lane'] === $lane)->values()->all();
}

test('a weekday with no opening-hours ranges is a single "cerrado" band for the whole grid', function () {
    $result = buildDay(ranges: collect());

    expect($result['pieces'])->toHaveCount(1);
    expect($result['pieces'][0])->toMatchArray(['kind' => 'band', 'bandType' => 'cerrado', 'top' => 0]);
    expect($result['pieces'][0]['height'])->toBe(DayTimeline::pxFromMinutes(1140 - 540));
});

/**
 * Review finding M1: a day with no opening-hours range at all still shows
 * a confirmed appointment forced into it ("Guardar igualmente"), instead
 * of hiding it behind a single blind "Cerrado" band — same treatment a
 * real ScheduleBlock full closure already gets (PRF-022).
 */
test('a day with no opening-hours range still shows a forced appointment, shading the other lane "cerrado"', function () {
    $survivor = Appointment::factory()->create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00']);

    $result = buildDay(ranges: collect(), appointments: collect([$survivor]));

    $kinds = array_map(fn ($p) => $p['kind'].':'.($p['bandType'] ?? ''), $result['pieces']);
    expect($kinds)->toBe(['band:cerrado', 'open:', 'band:cerrado']);

    $openPiece = $result['pieces'][1];
    $lane0Appointments = array_values(array_filter(laneSegments($openPiece, 0), fn ($s) => $s['type'] === 'appointment'));
    expect($lane0Appointments)->toHaveCount(1);
    expect($lane0Appointments[0]['appointment']->is($survivor))->toBeTrue();
    expect(array_filter(laneSegments($openPiece, 1), fn ($s) => $s['type'] === 'cierre-parcial'))->not->toBeEmpty();
});

/**
 * Review finding H1: a confirmed appointment forced outside this day's own
 * opening hours — reachable only via "Guardar igualmente" — still gets a
 * lane once the caller has extended the grid to cover it
 * (DayTimeline::extendBounds(), exercised at the controller level): it
 * must not be left invisible inside a plain "fuera de horario" band.
 */
test('a confirmed appointment outside this day\'s own hours still gets a lane once the grid is extended to cover it', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 19:30', 'ends_at' => '2030-01-08 20:00']);

    $result = buildDay(appointments: collect([$appointment]), gridEnd: 1200); // extended to 20:00, as extendBounds() would

    $kinds = array_map(fn ($p) => $p['kind'].':'.($p['bandType'] ?? ''), $result['pieces']);
    expect($kinds)->toBe(['open:', 'band:fuera-horario', 'open:']);

    $survivorPiece = end($result['pieces']);
    $lane0Appointments = array_values(array_filter(laneSegments($survivorPiece, 0), fn ($s) => $s['type'] === 'appointment'));
    expect($lane0Appointments)->toHaveCount(1);
    expect($lane0Appointments[0]['appointment']->is($appointment))->toBeTrue();
});

/**
 * Review finding L1: positions use wall-clock minutes (hour*60+minute),
 * not Carbon's diffInMinutes() (real elapsed time, which drifts an hour on
 * the two Sundays a year Europe/Madrid changes its clock).
 */
test('appointment positions use clock minutes, not real elapsed time, across the spring DST change', function () {
    $day = CarbonImmutable::parse('2026-03-29', 'Europe/Madrid')->startOfDay(); // DST spring-forward Sunday
    $ranges = collect([OpeningHour::make(['weekday' => $day->isoWeekday(), 'opens_at' => '09:00:00', 'closes_at' => '19:00:00'])]);
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-03-29 10:00:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-03-29 10:30:00', 'Europe/Madrid'),
    ]);

    $result = DayTimeline::build($day, 9 * 60, 19 * 60, 2, $ranges, collect([$appointment]), collect(), $day->subDay());

    $appointmentSegment = collect($result['pieces'][0]['segments'])->first(fn ($s) => $s['type'] === 'appointment');
    expect($appointmentSegment['top'])->toBe(DayTimeline::pxFromMinutes(60)); // 10:00 is 60 clock-minutes after the 09:00 grid start
});

test('appointment positions use clock minutes, not real elapsed time, across the autumn DST change', function () {
    $day = CarbonImmutable::parse('2026-10-25', 'Europe/Madrid')->startOfDay(); // DST fall-back Sunday
    $ranges = collect([OpeningHour::make(['weekday' => $day->isoWeekday(), 'opens_at' => '09:00:00', 'closes_at' => '19:00:00'])]);
    $appointment = Appointment::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-10-25 10:00:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-10-25 10:30:00', 'Europe/Madrid'),
    ]);

    $result = DayTimeline::build($day, 9 * 60, 19 * 60, 2, $ranges, collect([$appointment]), collect(), $day->subDay());

    $appointmentSegment = collect($result['pieces'][0]['segments'])->first(fn ($s) => $s['type'] === 'appointment');
    expect($appointmentSegment['top'])->toBe(DayTimeline::pxFromMinutes(60));
});

/**
 * Review finding L2: a free half hour that has already passed (today,
 * before "ahora") is not tappable; one that has not is.
 */
test('a free half hour before "ahora" today is not tappable, one after it is', function () {
    $now = CarbonImmutable::parse('2030-01-08 10:15');

    $result = buildDay(now: $now);
    $lane0 = laneSegments($result['pieces'][0], 0);

    $before = collect($lane0)->first(fn ($s) => $s['start'] === 540); // 09:00, already past
    $after = collect($lane0)->first(fn ($s) => $s['start'] === 630); // 10:30, still ahead of 10:15

    expect($before['tappable'])->toBeFalse();
    expect($after['tappable'])->toBeTrue();
});

test('every free half hour of a fully past day is not tappable', function () {
    $now = CarbonImmutable::parse('2030-01-09 08:00'); // the day after

    $result = buildDay(now: $now);
    $lane0 = collect(laneSegments($result['pieces'][0], 0))->filter(fn ($s) => $s['type'] === 'free');

    expect($lane0->every(fn ($s) => ! $s['tappable']))->toBeTrue();
});

/**
 * PRF-112: the grid can be wider than a specific day's own opening hours
 * (the week's earliest/latest), and the parts outside that day's own
 * ranges show "fuera de horario".
 */
test('time outside this day\'s own ranges, but inside the shared grid, is "fuera de horario"', function () {
    $result = buildDay(gridStart: 480, gridEnd: 1200); // 08:00-20:00 grid, day open 09:00-19:00

    $kinds = array_map(fn ($p) => $p['kind'].':'.($p['bandType'] ?? ''), $result['pieces']);
    expect($kinds)->toBe(['band:fuera-horario', 'open:', 'band:fuera-horario']);
});

/**
 * A lunch gap between two tramos on the same day.
 */
test('a gap between two tramos on the same day is "fuera de horario"', function () {
    $day = CarbonImmutable::parse('2030-01-08')->startOfDay();
    $ranges = collect([
        OpeningHour::make(['weekday' => $day->isoWeekday(), 'opens_at' => '09:00:00', 'closes_at' => '13:00:00']),
        OpeningHour::make(['weekday' => $day->isoWeekday(), 'opens_at' => '15:00:00', 'closes_at' => '19:00:00']),
    ]);

    $result = buildDay(ranges: $ranges);

    $kinds = array_map(fn ($p) => $p['kind'].':'.($p['bandType'] ?? ''), $result['pieces']);
    expect($kinds)->toBe(['open:', 'band:fuera-horario', 'open:']);
    // 13:00-15:00 gap.
    expect($result['pieces'][1]['top'])->toBe(DayTimeline::pxFromMinutes(13 * 60 - 540));
    expect($result['pieces'][1]['height'])->toBe(DayTimeline::pxFromMinutes(2 * 60));
});

test('an open day with no appointments is a single open piece with every lane free in 30-minute chunks', function () {
    $result = buildDay();

    expect($result['pieces'])->toHaveCount(1);
    $piece = $result['pieces'][0];
    expect($piece['kind'])->toBe('open');
    expect($piece['top'])->toBe(0);
    expect($result['lanes'])->toBe(2);
    expect($piece['maxLanes'])->toBe(2);

    foreach ([0, 1] as $lane) {
        $segments = laneSegments($piece, $lane);
        // 600 minutes / 30 = 20 free half-hour chunks, each its own tap target (review finding N1).
        expect($segments)->toHaveCount(20);
        foreach ($segments as $segment) {
            expect($segment['type'])->toBe('free');
            expect($segment['tappable'])->toBeTrue();
            expect($segment['height'])->toBe(DayTimeline::pxFromMinutes(30));
        }
    }
});

test('a single appointment occupies lane 0, with free halves before and after in both lanes', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);

    $result = buildDay(appointments: collect([$appointment]));
    $piece = $result['pieces'][0];

    $lane0 = laneSegments($piece, 0);
    expect(array_column($lane0, 'type'))->toBe(array_merge(array_fill(0, 2, 'free'), ['appointment'], array_fill(0, 17, 'free')));
    $appointmentSegment = $lane0[2];
    expect($appointmentSegment['appointment']->is($appointment))->toBeTrue();
    expect($appointmentSegment['top'])->toBe(DayTimeline::pxFromMinutes(60));
    expect($appointmentSegment['height'])->toBe(DayTimeline::pxFromMinutes(30));

    $lane1 = laneSegments($piece, 1);
    expect($lane1)->toHaveCount(20);
    expect(array_unique(array_column($lane1, 'type')))->toBe(['free']);
});

test('two overlapping appointments go to different lanes', function () {
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);
    $b = Appointment::factory()->create(['starts_at' => '2030-01-08 10:30', 'ends_at' => '2030-01-08 11:30']);

    $result = buildDay(appointments: collect([$a, $b]));
    $piece = $result['pieces'][0];

    $lane0Appointments = array_values(array_filter(laneSegments($piece, 0), fn ($s) => $s['type'] === 'appointment'));
    $lane1Appointments = array_values(array_filter(laneSegments($piece, 1), fn ($s) => $s['type'] === 'appointment'));

    expect($lane0Appointments)->toHaveCount(1);
    expect($lane1Appointments)->toHaveCount(1);
    expect($lane0Appointments[0]['appointment']->is($a))->toBeTrue();
    expect($lane1Appointments[0]['appointment']->is($b))->toBeTrue();
});

test('a cancelled appointment does not occupy a lane: that time stays free', function () {
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);

    $result = buildDay();
    $piece = $result['pieces'][0];

    expect(array_filter($piece['segments'], fn ($s) => $s['type'] === 'appointment'))->toBeEmpty();
    foreach ([0, 1] as $lane) {
        expect(array_unique(array_column(laneSegments($piece, $lane), 'type')))->toBe(['free']);
    }
});

/**
 * PRF-112: a full closure with no surviving appointment is a single
 * "cierre" band across every lane.
 */
test('a full closure with no appointment becomes a "cierre" band', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00', 'capacity_reduction' => null]);

    $result = buildDay(blocks: collect([ScheduleBlock::first()]));

    $kinds = array_map(fn ($p) => $p['kind'].':'.($p['bandType'] ?? ''), $result['pieces']);
    expect($kinds)->toBe(['open:', 'band:cierre', 'open:']);
});

/**
 * PRF-112 edge case: a full closure that still has a confirmed appointment
 * surviving inside it (PRF-022) must keep showing that appointment, not
 * hide it under the band — the other lane(s) shade "cierre-parcial"
 * instead.
 */
test('a full closure with a surviving appointment keeps showing the appointment, shading the other lane', function () {
    $survivor = Appointment::factory()->create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00']);
    ScheduleBlock::create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00', 'capacity_reduction' => null]);

    $result = buildDay(appointments: collect([$survivor]), blocks: collect([ScheduleBlock::first()]));

    $kinds = array_map(fn ($p) => $p['kind'], $result['pieces']);
    expect($kinds)->toBe(['open']); // no separate "cierre" band: the whole day stays one open piece

    $piece = $result['pieces'][0];
    $lane0Appointments = array_values(array_filter(laneSegments($piece, 0), fn ($s) => $s['type'] === 'appointment'));
    expect($lane0Appointments)->toHaveCount(1);
    expect($lane0Appointments[0]['appointment']->is($survivor))->toBeTrue();

    // Lane 1 has no appointment, so 13:00-14:00 (effective capacity 0 there) shades "cierre-parcial" as one merged segment.
    $lane1Closed = array_values(array_filter(laneSegments($piece, 1), fn ($s) => $s['type'] === 'cierre-parcial'));
    expect($lane1Closed)->toHaveCount(1);
    expect($lane1Closed[0]['top'])->toBe(DayTimeline::pxFromMinutes(13 * 60 - 540));
    expect($lane1Closed[0]['height'])->toBe(DayTimeline::pxFromMinutes(60));
    expect(array_filter(laneSegments($piece, 1), fn ($s) => $s['type'] === 'appointment'))->toBeEmpty();
});

/**
 * Same edge case, but the surviving appointment is shorter than the
 * closure: once it ends, the remaining part of the closure (with no
 * appointment left in any lane) becomes its own clean "cierre" band
 * instead of staying part of the open piece.
 */
test('once a surviving appointment ends before the closure does, the rest becomes its own "cierre" band', function () {
    $survivor = Appointment::factory()->create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 13:30']);
    ScheduleBlock::create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00', 'capacity_reduction' => null]);

    $result = buildDay(appointments: collect([$survivor]), blocks: collect([ScheduleBlock::first()]));

    $kinds = array_map(fn ($p) => $p['kind'].':'.($p['bandType'] ?? ''), $result['pieces']);
    expect($kinds)->toBe(['open:', 'band:cierre', 'open:']);

    $firstPieceAppointments = array_values(array_filter($result['pieces'][0]['segments'], fn ($s) => $s['type'] === 'appointment'));
    expect($firstPieceAppointments)->toHaveCount(1);
    expect($firstPieceAppointments[0]['appointment']->is($survivor))->toBeTrue();
});

/**
 * PRF-113: a partial closure (capacity 2 -> 1) shades only the lane(s)
 * beyond the reduced effective capacity, leaving the others usable.
 */
test('a partial closure shades only the lane(s) beyond the reduced capacity', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00', 'capacity_reduction' => 1]);

    $result = buildDay(blocks: collect([ScheduleBlock::first()]));
    $piece = $result['pieces'][0];

    expect(array_filter(laneSegments($piece, 0), fn ($s) => $s['type'] !== 'free'))->toBeEmpty(); // lane 0 stays usable throughout

    $lane1Closed = array_values(array_filter(laneSegments($piece, 1), fn ($s) => $s['type'] === 'cierre-parcial'));
    expect($lane1Closed)->toHaveCount(1);
    expect($lane1Closed[0]['top'])->toBe(DayTimeline::pxFromMinutes(13 * 60 - 540));
    expect($lane1Closed[0]['height'])->toBe(DayTimeline::pxFromMinutes(60));
});

/**
 * PRF-109: more confirmed appointments at once than capacity (a "Guardar
 * igualmente" override) adds an extra lane, marked overCapacity.
 */
test('more simultaneous appointments than capacity add an extra lane marked overCapacity', function () {
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);
    $b = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);
    $c = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $result = buildDay(appointments: collect([$a, $b, $c]));

    expect($result['lanes'])->toBe(3);
    $piece = $result['pieces'][0];
    $lane2Appointment = collect(laneSegments($piece, 2))->first(fn ($s) => $s['type'] === 'appointment');
    $lane0Appointment = collect(laneSegments($piece, 0))->first(fn ($s) => $s['type'] === 'appointment');
    expect($lane2Appointment['overCapacity'])->toBeTrue();
    expect($lane0Appointment['overCapacity'])->toBeFalse();
});

/**
 * PRF-114: a free gap shorter than 30 minutes is not offered as its own
 * tap target.
 */
test('a free gap shorter than 30 minutes is not tappable', function () {
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);
    $b = Appointment::factory()->create(['starts_at' => '2030-01-08 10:45', 'ends_at' => '2030-01-08 11:15']);

    $result = buildDay(appointments: collect([$a, $b]));
    $lane0 = laneSegments($result['pieces'][0], 0);

    $shortGap = collect($lane0)->first(fn ($s) => $s['type'] === 'free' && $s['height'] === DayTimeline::pxFromMinutes(15));
    expect($shortGap)->not->toBeNull();
    expect($shortGap['tappable'])->toBeFalse();
});
