<?php

use App\Booking\DayTimeline;
use App\Models\Appointment;
use App\Models\OpeningHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Review finding N1 (coordinator, agenda-service-filter): DayTimeline::
 * markServiceFit() is presentation-only (never touches
 * AvailabilityCalculator), so these tests supply $fittingMinutes directly
 * instead of going through the full capacity check — isolating exactly
 * the part under review, which lane(s) get highlighted once a minute is
 * already known to fit.
 */
function buildDayWithAppointments(array $appointments, int $capacity = 2): array
{
    $day = CarbonImmutable::parse('2030-01-08')->startOfDay(); // Tuesday, default schedule 09:00-19:00
    $ranges = collect([OpeningHour::make(['weekday' => $day->isoWeekday(), 'opens_at' => '09:00:00', 'closes_at' => '19:00:00'])]);

    return DayTimeline::build($day, 9 * 60, 19 * 60, $capacity, $ranges, collect($appointments), collect(), $day->subDay());
}

/**
 * @return array|null the lane's segment starting at $minute, or null
 */
function segmentAt(array $timeline, int $lane, int $minute): ?array
{
    foreach ($timeline['pieces'] as $piece) {
        if ($piece['kind'] !== 'open') {
            continue;
        }

        foreach ($piece['segments'] as $segment) {
            if ($segment['lane'] === $lane && $segment['start'] === $minute) {
                return $segment;
            }
        }
    }

    return null;
}

/**
 * The exact scenario the coordinator found in local data: an hour-long
 * service "fits by capacity" at 11:00 even in plaza 1, which holds a
 * 15-minute appointment at 11:40 — only plaza 2 is free for the whole
 * hour, so only plaza 2 should be marked.
 */
test('only the lane free for the whole service is marked, not every lane that fits by capacity', function () {
    $josep = Appointment::factory()->create(['starts_at' => '2030-01-08 11:40', 'ends_at' => '2030-01-08 11:55']);

    $timeline = buildDayWithAppointments([$josep]);
    $timeline = DayTimeline::markServiceFit($timeline, [11 * 60], 60); // 11:00, 60 minutes

    expect(segmentAt($timeline, 0, 11 * 60)['fits'])->toBeFalse(); // plaza 1: the 11:40 appointment would collide
    expect(segmentAt($timeline, 1, 11 * 60)['fits'])->toBeTrue(); // plaza 2: free the whole hour
});

/**
 * Appointments staggered across different lanes (each interrupting its
 * own lane at a different moment, never simultaneously) can leave no lane
 * continuously free for the whole service even though the minute is a
 * given "fits" candidate: the first free lane at that minute (lane 0,
 * same one AppointmentLaneAssigner itself would pick for a new
 * appointment) is marked anyway, since there is no safer choice.
 */
test('with no lane continuously free, the first free lane at that minute is marked', function () {
    // 10:30-11:00 (lane 0) and 10:45-11:15 (lane 1, forced there because
    // it overlaps the first): together they touch every lane somewhere
    // inside the 10:00-11:00 candidate window, without ever being
    // simultaneous enough to matter for capacity.
    $a = Appointment::factory()->create(['starts_at' => '2030-01-08 10:30', 'ends_at' => '2030-01-08 11:00']);
    $b = Appointment::factory()->create(['starts_at' => '2030-01-08 10:45', 'ends_at' => '2030-01-08 11:15']);

    $timeline = buildDayWithAppointments([$a, $b]);
    $timeline = DayTimeline::markServiceFit($timeline, [10 * 60], 60); // 10:00, 60 minutes

    expect(segmentAt($timeline, 0, 10 * 60)['fits'])->toBeTrue(); // first free lane
    expect(segmentAt($timeline, 1, 10 * 60)['fits'])->toBeFalse();
});

/**
 * Capacity 1 (a single lane): the fallback above degenerates to "mark the
 * only lane there is", even though it is not actually free the whole
 * time — there is nowhere else to point to, same rule as the staggered
 * case above, just with one lane instead of several.
 */
test('with capacity 1, the only lane is marked even when it is not free the whole time', function () {
    $josep = Appointment::factory()->create(['starts_at' => '2030-01-08 11:40', 'ends_at' => '2030-01-08 11:55']);

    $timeline = buildDayWithAppointments([$josep], capacity: 1);
    $timeline = DayTimeline::markServiceFit($timeline, [11 * 60], 60); // 11:00, 60 minutes

    expect($timeline['lanes'])->toBe(1);
    expect(segmentAt($timeline, 0, 11 * 60)['fits'])->toBeTrue();
});

/**
 * A candidate minute absent from $fittingMinutes is never marked,
 * regardless of any lane's own availability.
 */
test('a minute that does not fit by capacity is never marked, in any lane', function () {
    $timeline = buildDayWithAppointments([]);
    $timeline = DayTimeline::markServiceFit($timeline, [], 60); // nothing fits

    expect(segmentAt($timeline, 0, 9 * 60)['fits'])->toBeFalse();
    expect(segmentAt($timeline, 1, 9 * 60)['fits'])->toBeFalse();
});
