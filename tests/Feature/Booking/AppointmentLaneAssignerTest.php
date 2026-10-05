<?php

use App\Booking\AppointmentLaneAssigner;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laneAppointment(string $starts, string $ends, bool $cancelled = false): Appointment
{
    $appointment = Appointment::factory()->make(['starts_at' => $starts, 'ends_at' => $ends]);

    if ($cancelled) {
        $appointment->status = \App\Enums\AppointmentStatus::Cancelled;
    }

    // An id is needed to key the lane map; factory ->make() leaves it null.
    static $nextId = 1;
    $appointment->id = $nextId++;

    return $appointment;
}

/**
 * PRF-109 / PRF-110: fixed lanes, one per unit of capacity; appointments
 * get assigned by the "first free lane" rule.
 */
test('a single appointment on an otherwise empty day gets lane 0, with the full capacity in lanes', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 10:30');

    $result = AppointmentLaneAssigner::assign(collect([$a]), capacity: 2);

    expect($result['lanes'][$a->id])->toBe(0);
    expect($result['maxLanes'])->toBe(2);
});

test('two overlapping appointments get different lanes', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');
    $b = laneAppointment('2030-01-08 10:30', '2030-01-08 11:30');

    $result = AppointmentLaneAssigner::assign(collect([$a, $b]), capacity: 2);

    expect($result['lanes'][$a->id])->toBe(0);
    expect($result['lanes'][$b->id])->toBe(1);
    expect($result['maxLanes'])->toBe(2);
});

/**
 * An appointment that ends exactly when another starts does not overlap it
 * (same rule as AvailabilityCalculator), so it can reuse the same lane.
 */
test('back-to-back appointments (one ends when the other starts) share a lane', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 10:30');
    $b = laneAppointment('2030-01-08 10:30', '2030-01-08 11:00');

    $result = AppointmentLaneAssigner::assign(collect([$a, $b]), capacity: 2);

    expect($result['lanes'][$a->id])->toBe(0);
    expect($result['lanes'][$b->id])->toBe(0);
    expect($result['maxLanes'])->toBe(2);
});

test('a third appointment overlapping the first two reuses a lane once one frees up', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 10:30');
    $b = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');
    $c = laneAppointment('2030-01-08 10:30', '2030-01-08 11:00');

    $result = AppointmentLaneAssigner::assign(collect([$a, $b, $c]), capacity: 2);

    expect($result['lanes'][$a->id])->toBe(0);
    expect($result['lanes'][$b->id])->toBe(1);
    // $a ended at 10:30, freeing lane 0, so $c (10:30-11:00) reuses it.
    expect($result['lanes'][$c->id])->toBe(0);
    expect($result['maxLanes'])->toBe(2);
});

/**
 * PRF-109: more simultaneous confirmed appointments than capacity (an
 * admin "Guardar igualmente" override) adds extra lanes beyond capacity,
 * never dropping an appointment from the grid.
 */
test('more overlapping appointments than capacity get extra lanes beyond it', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');
    $b = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');
    $c = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');

    $result = AppointmentLaneAssigner::assign(collect([$a, $b, $c]), capacity: 2);

    $lanes = [$result['lanes'][$a->id], $result['lanes'][$b->id], $result['lanes'][$c->id]];
    sort($lanes);
    expect($lanes)->toBe([0, 1, 2]);
    expect($result['maxLanes'])->toBe(3);
    expect($result['overCapacity'][$a->id])->toBeFalse();
    expect($result['overCapacity'][$b->id])->toBeFalse();
    expect($result['overCapacity'][$c->id])->toBeTrue();
});

/**
 * PRF-110: a cancelled appointment never occupies a lane.
 */
test('a cancelled appointment is left out of the lane assignment entirely', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00', cancelled: true);
    $b = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');

    $result = AppointmentLaneAssigner::assign(collect([$a, $b]), capacity: 2);

    expect($result['lanes'])->not->toHaveKey($a->id);
    expect($result['lanes'][$b->id])->toBe(0);
    expect($result['maxLanes'])->toBe(2);
});

test('an empty day keeps the full capacity in lanes with nothing assigned', function () {
    $result = AppointmentLaneAssigner::assign(collect(), capacity: 2);

    expect($result['lanes'])->toBe([]);
    expect($result['maxLanes'])->toBe(2);
});

test('assignment does not depend on input order (sorts by start time itself)', function () {
    $a = laneAppointment('2030-01-08 10:00', '2030-01-08 11:00');
    $b = laneAppointment('2030-01-08 10:30', '2030-01-08 11:30');

    $forward = AppointmentLaneAssigner::assign(collect([$a, $b]), capacity: 2);
    $reversed = AppointmentLaneAssigner::assign(collect([$b, $a]), capacity: 2);

    expect($forward['lanes'])->toBe($reversed['lanes']);
});
