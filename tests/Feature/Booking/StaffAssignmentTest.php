<?php

use App\Booking\StaffAssignment;

test('it shares out stretches so nobody does two at once or works while away', function () {
    // Machine 0 is busy 10-11, machine 1 is away 11-12.
    $busy = [0 => [[10, 11]], 1 => [[11, 12]]];

    expect(StaffAssignment::solve(2, $busy, [[9, 10], [10, 11], [11, 12]]))->toEqual([0 => 0, 1 => 1, 2 => 0])
        ->and(StaffAssignment::solve(2, $busy, [[10, 12]]))->toBeNull()
        ->and(StaffAssignment::solve(2, [], [[1, 3], [2, 4], [3, 5]]))->not->toBeNull()
        ->and(StaffAssignment::solve(2, [], [[1, 3], [2, 4], [2, 5]]))->toBeNull();
});

test('it finds an assignment a greedy choice would miss', function () {
    // The first stretch must go to machine 1, although machine 0 is free
    // then: machine 0 is needed for the second one.
    $busy = [1 => [[20, 30]]];

    expect(StaffAssignment::solve(2, $busy, [[0, 10], [5, 25]]))->toEqual([0 => 1, 1 => 0]);
});

test('it answers no, never yes, when it runs out of steps', function () {
    expect(StaffAssignment::solve(2, [], [[0, 1], [1, 2], [2, 3]], [], limit: 1))->toBeNull()
        ->and(StaffAssignment::solve(0, [], [[0, 1]]))->toBeNull()
        ->and(StaffAssignment::solve(0, [], []))->toBe([]);
});

test('it groups the stretches that overlap, directly or through others', function () {
    // Ending exactly when another starts is not an overlap.
    expect(StaffAssignment::groups([[0, 10], [20, 30], [5, 15], [12, 18], [30, 40]]))->toBe([[0, 2, 3], [1], [4]]);
});
