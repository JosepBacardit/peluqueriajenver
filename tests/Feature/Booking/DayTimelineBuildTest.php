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
 * one tramo) unless $ranges/$blocks/$appointments override it.
 */
function buildDay(
    string $date = '2030-01-08',
    ?Collection $ranges = null,
    ?Collection $appointments = null,
    ?Collection $blocks = null,
    int $capacity = 2,
    int $gridStart = 540,
    int $gridEnd = 1140,
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
    );
}

test('a weekday with no opening-hours ranges is a single "cerrado" band for the whole grid', function () {
    $result = buildDay(ranges: collect());

    expect($result['pieces'])->toHaveCount(1);
    expect($result['pieces'][0])->toMatchArray(['kind' => 'band', 'bandType' => 'cerrado', 'top' => 0]);
    expect($result['pieces'][0]['height'])->toBe(DayTimeline::pxFromMinutes(1140 - 540));
});

test('an open day with no appointments is a single open piece with every lane free', function () {
    $result = buildDay();

    expect($result['pieces'])->toHaveCount(1);
    $piece = $result['pieces'][0];
    expect($piece['kind'])->toBe('open');
    expect($piece['top'])->toBe(0);
    expect($result['lanes'])->toBe(2);
    expect($piece['laneSegments'])->toHaveCount(2);

    foreach ($piece['laneSegments'] as $segments) {
        expect($segments)->toHaveCount(1);
        expect($segments[0]['type'])->toBe('free');
        expect($segments[0]['tappable'])->toBeTrue();
        expect($segments[0]['height'])->toBe(DayTimeline::pxFromMinutes(600));
    }
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

test('a single appointment occupies lane 0, with free space before and after in both lanes', function () {
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);

    $result = buildDay(appointments: collect([$appointment]));

    $piece = $result['pieces'][0];
    $lane0 = $piece['laneSegments'][0];
    $lane1 = $piece['laneSegments'][1];

    expect(array_column($lane0, 'type'))->toBe(['free', 'appointment', 'free']);
    expect($lane0[1]['appointment']->is($appointment))->toBeTrue();
    expect($lane0[1]['top'])->toBe(DayTimeline::pxFromMinutes(60));
    expect($lane0[1]['height'])->toBe(DayTimeline::pxFromMinutes(30));
    expect(array_column($lane1, 'type'))->toBe(['free']);
});

test('two overlapping appointments go to different lanes, each with its own free gaps', function () {
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);
    $b = Appointment::factory()->create(['starts_at' => '2030-01-08 10:30', 'ends_at' => '2030-01-08 11:30']);

    $result = buildDay(appointments: collect([$a, $b]));
    $piece = $result['pieces'][0];

    expect(array_column($piece['laneSegments'][0], 'type'))->toBe(['free', 'appointment', 'free']);
    expect(array_column($piece['laneSegments'][1], 'type'))->toBe(['free', 'appointment', 'free']);
    expect($piece['laneSegments'][0][1]['appointment']->is($a))->toBeTrue();
    expect($piece['laneSegments'][1][1]['appointment']->is($b))->toBeTrue();
});

test('a cancelled appointment does not occupy a lane: that time stays free', function () {
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);

    $result = buildDay();
    $piece = $result['pieces'][0];

    foreach ($piece['laneSegments'] as $segments) {
        expect(array_column($segments, 'type'))->toBe(['free']);
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
 * PRF-112 edge case (same as review finding H1 for vista Semana): a full
 * closure that still has a confirmed appointment surviving inside it
 * (PRF-022) must keep showing that appointment, not hide it under the
 * band — the other lane(s) shade "cierre-parcial" instead.
 */
test('a full closure with a surviving appointment keeps showing the appointment, shading the other lane', function () {
    $survivor = Appointment::factory()->create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00']);
    ScheduleBlock::create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00', 'capacity_reduction' => null]);

    $result = buildDay(appointments: collect([$survivor]), blocks: collect([ScheduleBlock::first()]));

    $kinds = array_map(fn ($p) => $p['kind'], $result['pieces']);
    expect($kinds)->toBe(['open']); // no separate "cierre" band: the whole day stays one open piece

    $piece = $result['pieces'][0];
    expect(array_column($piece['laneSegments'][0], 'type'))->toBe(['free', 'appointment', 'free']);
    expect($piece['laneSegments'][0][1]['appointment']->is($survivor))->toBeTrue();
    // Lane 1 has no appointment, so 13:00-14:00 (effective capacity 0 there) shades "cierre-parcial".
    expect(array_column($piece['laneSegments'][1], 'type'))->toBe(['free', 'cierre-parcial', 'free']);
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
    expect($result['pieces'][0]['laneSegments'][0][1]['appointment']->is($survivor))->toBeTrue();
});

/**
 * PRF-113: a partial closure (capacity 2 -> 1) shades only the lane(s)
 * beyond the reduced effective capacity, leaving the others usable.
 */
test('a partial closure shades only the lane(s) beyond the reduced capacity', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-08 13:00', 'ends_at' => '2030-01-08 14:00', 'capacity_reduction' => 1]);

    $result = buildDay(blocks: collect([ScheduleBlock::first()]));
    $piece = $result['pieces'][0];

    expect(array_column($piece['laneSegments'][0], 'type'))->toBe(['free']); // lane 0 stays usable throughout
    expect(array_column($piece['laneSegments'][1], 'type'))->toBe(['free', 'cierre-parcial', 'free']);
    expect($piece['laneSegments'][1][1]['top'])->toBe(DayTimeline::pxFromMinutes(13 * 60 - 540));
    expect($piece['laneSegments'][1][1]['height'])->toBe(DayTimeline::pxFromMinutes(60));
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
    expect($piece['laneSegments'][2][1]['type'])->toBe('appointment');
    expect($piece['laneSegments'][2][1]['overCapacity'])->toBeTrue();
    expect($piece['laneSegments'][0][1]['overCapacity'])->toBeFalse();
});

/**
 * PRF-114: a free gap shorter than 30 minutes is not offered as its own
 * tap target.
 */
test('a free gap shorter than 30 minutes is not tappable', function () {
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30']);
    $b = Appointment::factory()->create(['starts_at' => '2030-01-08 10:45', 'ends_at' => '2030-01-08 11:15']);

    $result = buildDay(appointments: collect([$a, $b]));
    $lane0 = $result['pieces'][0]['laneSegments'][0];

    // free(before) / appointment a / free(15 min, not tappable) / appointment b / free(after)
    expect(array_column($lane0, 'type'))->toBe(['free', 'appointment', 'free', 'appointment', 'free']);
    expect($lane0[2]['tappable'])->toBeFalse();
    expect($lane0[2]['height'])->toBe(DayTimeline::pxFromMinutes(15));
});
