<?php

namespace App\Booking;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Assigns each active stretch of the day's confirmed appointments to a
 * fixed lane (one per unit of capacity, PRF-109), so the admin agenda's
 * timeline grid can place them side by side like Google Calendar, without
 * ever mixing them up with a particular hairdresser — lanes are purely
 * about capacity.
 *
 * An appointment with no waits is one stretch, from its start to its end.
 * One with waits (PRF-151) is one stretch per active part: the lane is
 * free while it waits, and its next stretch may land in another lane when
 * its own is taken by then — another hairdresser finishing it (PRF-153).
 *
 * Pure computation: no queries, only the appointments the caller already
 * loaded for that day.
 */
class AppointmentLaneAssigner
{
    /**
     * Stretches are sorted by start time (then end), and each goes into a
     * lane whose last stretch has already ended by the time it starts (the
     * same "does not overlap" rule as everywhere else: ending exactly when
     * another starts is not an overlap): the lane of the appointment's
     * previous stretch when it is free, otherwise the lowest-numbered free
     * one. When every existing lane is still busy, a new one is added —
     * this is how a day goes over capacity (PRF-109) without ever dropping
     * an appointment from the grid. Taking the stretches in start order,
     * any free lane is as good as another, so preferring the previous one
     * never needs more lanes than the most stretches at the same time.
     *
     * Keys are self::key(): "{appointment id}:{stretch index}".
     *
     * @param  Collection<int, Appointment>  $appointments  this day's, any status
     * @return array{lanes: array<string, int>, maxLanes: int, overCapacity: array<string, bool>, stretches: list<array{key: string, appointment: Appointment, index: int, count: int, start: CarbonImmutable, end: CarbonImmutable}>}
     *                                                                                                                                                                                                                                 lanes: stretch key => 0-based lane index.
     *                                                                                                                                                                                                                                 maxLanes: max($capacity, lanes actually used) — the grid always
     *                                                                                                                                                                                                                                 renders at least $capacity lanes, even with fewer or no
     *                                                                                                                                                                                                                                 appointments.
     *                                                                                                                                                                                                                                 overCapacity: stretch key => whether its lane index is beyond
     *                                                                                                                                                                                                                                 the configured capacity.
     *                                                                                                                                                                                                                                 stretches: every confirmed appointment's active stretches, in
     *                                                                                                                                                                                                                                 the order they were assigned.
     */
    public static function assign(Collection $appointments, int $capacity): array
    {
        $stretches = self::stretches($appointments);

        $laneEndsAt = []; // lane index => end of its last stretch
        $lanes = [];

        foreach ($stretches as $stretch) {
            $previous = $stretch['index'] > 0 ? $lanes[self::key($stretch['appointment'], $stretch['index'] - 1)] : null;
            $lane = $previous !== null && $laneEndsAt[$previous]->lte($stretch['start'])
                ? $previous
                : self::firstFreeLane($laneEndsAt, $stretch['start']);
            $laneEndsAt[$lane] = $stretch['end'];
            $lanes[$stretch['key']] = $lane;
        }

        $maxLanes = max($capacity, count($laneEndsAt));

        return [
            'lanes' => $lanes,
            'maxLanes' => $maxLanes,
            'overCapacity' => array_map(fn (int $lane) => $lane >= $capacity, $lanes),
            'stretches' => $stretches,
        ];
    }

    public static function key(Appointment $appointment, int $index): string
    {
        return $appointment->id.':'.$index;
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{key: string, appointment: Appointment, index: int, count: int, start: CarbonImmutable, end: CarbonImmutable}>
     */
    private static function stretches(Collection $appointments): array
    {
        return $appointments
            ->filter(fn (Appointment $appointment) => $appointment->isConfirmed())
            ->flatMap(function (Appointment $appointment) {
                $intervals = TimeProfile::fromAppointment($appointment)->activeIntervals($appointment->starts_at);

                return array_map(fn (array $interval, int $index) => [
                    'key' => self::key($appointment, $index),
                    'appointment' => $appointment,
                    'index' => $index,
                    'count' => count($intervals),
                    'start' => $interval[0],
                    'end' => $interval[1],
                ], $intervals, array_keys($intervals));
            })
            ->sort(fn (array $a, array $b) => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end'])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, CarbonInterface>  $laneEndsAt
     */
    private static function firstFreeLane(array $laneEndsAt, CarbonInterface $start): int
    {
        for ($lane = 0; $lane < count($laneEndsAt); $lane++) {
            if ($laneEndsAt[$lane]->lte($start)) {
                return $lane;
            }
        }

        return count($laneEndsAt);
    }
}
