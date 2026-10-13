<?php

namespace App\Booking;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Assigns each active stretch of the day's confirmed appointments to a
 * fixed lane, so the admin agenda's timeline grid can place them side by
 * side like Google Calendar. There is one lane per active hairdresser
 * (PRF-171; one per unit of capacity, PRF-109, when the caller names
 * none).
 *
 * An appointment with a hairdresser goes in her lane ($laneOfHairdresser).
 * One with none ("Cualquiera") goes in a lane free for the whole stretch,
 * and never in the lane of a hairdresser while she is away ($laneBusy). An
 * appointment with no waits is one stretch, from its start to its end. One
 * with waits (PRF-151) is one stretch per active part: the lane is free
 * while it waits, and an unassigned appointment's next stretch may land in
 * another lane when its own is taken by then — another hairdresser
 * finishing it (PRF-153).
 *
 * Pure computation: no queries, only the appointments the caller already
 * loaded for that day.
 */
class AppointmentLaneAssigner
{
    /**
     * First the stretches of the appointments with a hairdresser go into
     * her lane, in start order (a second one at the same time, which only
     * "Guardar igualmente" can save, is placed like an unassigned one).
     * Then the rest, sorted by start time (then end), each into a lane
     * with nothing placed during the whole stretch (ending exactly when
     * another starts is not an overlap): the lane of the appointment's
     * previous stretch when it is free, otherwise the first free one of
     * $preferredLanes (where the hairdressers who stand out in its services
     * are, PRF-173), otherwise the lowest-numbered free one. When no
     * existing lane is free, a new one is added — this is how a day goes
     * over capacity (PRF-109) without ever dropping an appointment from
     * the grid.
     *
     * With no hairdresser named, this is exactly the old rule: taking the
     * stretches in start order, a lane is free for the whole stretch as
     * soon as its last stretch has ended, so it never needs more lanes than
     * the most stretches at the same time.
     *
     * Keys are self::key(): "{appointment id}:{stretch index}".
     *
     * @param  Collection<int, Appointment>  $appointments  this day's, any status
     * @param  array<int, int>  $laneOfHairdresser  hairdresser id => her lane (0-based, below $capacity)
     * @param  array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>>  $laneBusy  lane => times its hairdresser is away
     * @param  array<int, list<int>>  $preferredLanes  appointment id => lanes to prefer for it
     * @return array{lanes: array<string, int>, maxLanes: int, overCapacity: array<string, bool>, stretches: list<array{key: string, appointment: Appointment, index: int, count: int, start: CarbonImmutable, end: CarbonImmutable}>}
     *                                                                                                                                                                                                                                 lanes: stretch key => 0-based lane index.
     *                                                                                                                                                                                                                                 maxLanes: max($capacity, lanes actually used) — the grid always
     *                                                                                                                                                                                                                                 renders at least $capacity lanes, even with fewer or no
     *                                                                                                                                                                                                                                 appointments.
     *                                                                                                                                                                                                                                 overCapacity: stretch key => whether its lane index is beyond
     *                                                                                                                                                                                                                                 the configured capacity.
     *                                                                                                                                                                                                                                 stretches: every confirmed appointment's active stretches, in
     *                                                                                                                                                                                                                                 start order.
     */
    public static function assign(Collection $appointments, int $capacity, array $laneOfHairdresser = [], array $laneBusy = [], array $preferredLanes = []): array
    {
        $stretches = self::stretches($appointments);

        /** @var array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>> $placed lane => intervals placed in it */
        $placed = [];

        for ($lane = 0; $lane < $capacity; $lane++) {
            $placed[$lane] = $laneBusy[$lane] ?? [];
        }

        $lanes = [];
        $unplaced = [];

        foreach ($stretches as $stretch) {
            $lane = $laneOfHairdresser[$stretch['appointment']->hairdresser_id ?? 0] ?? null;

            if ($lane !== null && $lane < $capacity && self::isFree($placed[$lane], $stretch['start'], $stretch['end'])) {
                $placed[$lane][] = [$stretch['start'], $stretch['end']];
                $lanes[$stretch['key']] = $lane;
            } else {
                $unplaced[] = $stretch;
            }
        }

        // Review M1: group by group (stretches that overlap), keep the
        // greedy placement when it fits in the hairdressers' lanes; when it
        // does not, look for an exact one (StaffAssignment, the same search
        // AvailabilityCalculator uses), and only if there is none send
        // what does not fit to "Sobre capacidad".
        $intervals = array_map(fn (array $stretch) => [$stretch['start']->getTimestamp(), $stretch['end']->getTimestamp()], $unplaced);

        foreach (StaffAssignment::groups($intervals) as $group) {
            $greedyPlaced = $placed;
            $greedyLanes = $lanes;

            foreach ($group as $index) {
                $stretch = $unplaced[$index];
                $previous = $stretch['index'] > 0 ? ($greedyLanes[self::key($stretch['appointment'], $stretch['index'] - 1)] ?? null) : null;
                $free = array_values(array_filter(array_keys($greedyPlaced), fn (int $lane) => self::isFree($greedyPlaced[$lane], $stretch['start'], $stretch['end'])));
                $preferred = array_values(array_intersect($preferredLanes[$stretch['appointment']->id] ?? [], $free));

                $lane = match (true) {
                    $previous !== null && in_array($previous, $free, true) => $previous,
                    $preferred !== [] => $preferred[0],
                    $free !== [] => $free[0],
                    default => count($greedyPlaced),
                };

                $greedyPlaced[$lane][] = [$stretch['start'], $stretch['end']];
                $greedyLanes[$stretch['key']] = $lane;
            }

            $overflows = array_filter($group, fn (int $index) => $greedyLanes[$unplaced[$index]['key']] >= $capacity) !== [];
            $exact = $overflows
                ? self::exactPlacement($group, $unplaced, $intervals, $placed, $capacity, $lanes, $preferredLanes)
                : null;

            if ($exact === null) {
                [$placed, $lanes] = [$greedyPlaced, $greedyLanes];

                continue;
            }

            foreach ($exact as $index => $lane) {
                $placed[$lane][] = [$unplaced[$index]['start'], $unplaced[$index]['end']];
                $lanes[$unplaced[$index]['key']] = $lane;
            }
        }

        $maxLanes = max($capacity, count($placed));

        return [
            'lanes' => $lanes,
            'maxLanes' => $maxLanes,
            'overCapacity' => array_map(fn (int $lane) => $lane >= $capacity, $lanes),
            'stretches' => $stretches,
        ];
    }

    /**
     * An exact placement of one group of unassigned stretches in the
     * hairdressers' lanes (0 to $capacity - 1), preferring the lane of the
     * appointment's previous stretch and then $preferredLanes, or null
     * when there is none.
     *
     * @param  list<int>  $group  indexes into $unplaced
     * @param  list<array>  $unplaced
     * @param  list<array{0: int, 1: int}>  $intervals  $unplaced as timestamps
     * @param  array<int, list<array{0: CarbonImmutable, 1: CarbonImmutable}>>  $placed
     * @param  array<string, int>  $lanes
     * @param  array<int, list<int>>  $preferredLanes
     * @return array<int, int>|null index into $unplaced => lane
     */
    private static function exactPlacement(array $group, array $unplaced, array $intervals, array $placed, int $capacity, array $lanes, array $preferredLanes): ?array
    {
        $busy = [];

        for ($lane = 0; $lane < $capacity; $lane++) {
            $busy[$lane] = array_map(fn (array $interval) => [$interval[0]->getTimestamp(), $interval[1]->getTimestamp()], $placed[$lane] ?? []);
        }

        $jobs = array_map(fn (int $index) => $intervals[$index], $group);
        $preference = array_map(function (int $index) use ($unplaced, $lanes, $preferredLanes) {
            $stretch = $unplaced[$index];
            $previous = $stretch['index'] > 0 ? ($lanes[self::key($stretch['appointment'], $stretch['index'] - 1)] ?? null) : null;

            return array_values(array_unique(array_merge($previous === null ? [] : [$previous], $preferredLanes[$stretch['appointment']->id] ?? [])));
        }, $group);

        $solution = StaffAssignment::solve($capacity, $busy, $jobs, $preference);

        return $solution === null ? null : array_combine(array_map(fn (int $position) => $group[$position], array_keys($solution)), array_values($solution));
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
     * Whether nothing in $intervals overlaps [$start, $end).
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private static function isFree(array $intervals, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($intervals as [$busyStart, $busyEnd]) {
            if ($busyStart->lt($end) && $busyEnd->gt($start)) {
                return false;
            }
        }

        return true;
    }
}
