<?php

namespace App\Booking;

use App\Models\Appointment;
use Illuminate\Support\Collection;

/**
 * Assigns each confirmed appointment of a day to a fixed lane (one per
 * unit of capacity, PRF-109), so the admin agenda's timeline grid can
 * place them side by side like Google Calendar, without ever mixing them
 * up with a particular hairdresser — lanes are purely about capacity.
 *
 * Pure computation: no queries, only the appointments the caller already
 * loaded for that day.
 */
class AppointmentLaneAssigner
{
    /**
     * "First free lane" assignment: appointments are sorted by start time,
     * and each goes into the lowest-numbered lane whose last appointment
     * has already ended by the time this one starts (the same "does not
     * overlap" rule as everywhere else: ending exactly when another
     * starts is not an overlap). When every existing lane is still busy,
     * a new one is added — this is how a day goes over capacity (PRF-109)
     * without ever dropping an appointment from the grid.
     *
     * @param  Collection<int, Appointment>  $appointments  this day's, any status
     * @return array{lanes: array<int, int>, maxLanes: int, overCapacity: array<int, bool>}
     *         lanes: appointment id => 0-based lane index.
     *         maxLanes: max($capacity, lanes actually used) — the grid always
     *         renders at least $capacity lanes, even with fewer or no
     *         appointments.
     *         overCapacity: appointment id => whether its lane index is
     *         beyond the configured capacity.
     */
    public static function assign(Collection $appointments, int $capacity): array
    {
        $confirmed = $appointments
            ->filter(fn (Appointment $appointment) => $appointment->isConfirmed())
            ->sort(fn (Appointment $a, Appointment $b) => $a->starts_at <=> $b->starts_at ?: $a->ends_at <=> $b->ends_at)
            ->values();

        $laneEndsAt = []; // lane index => CarbonInterface of its last appointment's end
        $lanes = [];

        foreach ($confirmed as $appointment) {
            $lane = self::firstFreeLane($laneEndsAt, $appointment->starts_at);
            $laneEndsAt[$lane] = $appointment->ends_at;
            $lanes[$appointment->id] = $lane;
        }

        $maxLanes = max($capacity, count($laneEndsAt));

        return [
            'lanes' => $lanes,
            'maxLanes' => $maxLanes,
            'overCapacity' => array_map(fn (int $lane) => $lane >= $capacity, $lanes),
        ];
    }

    /**
     * @param  array<int, \Carbon\CarbonInterface>  $laneEndsAt
     */
    private static function firstFreeLane(array $laneEndsAt, \Carbon\CarbonInterface $start): int
    {
        for ($lane = 0; $lane < count($laneEndsAt); $lane++) {
            if ($laneEndsAt[$lane]->lte($start)) {
                return $lane;
            }
        }

        return count($laneEndsAt);
    }
}
