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

        foreach ($unplaced as $stretch) {
            $previous = $stretch['index'] > 0 ? ($lanes[self::key($stretch['appointment'], $stretch['index'] - 1)] ?? null) : null;
            $free = array_values(array_filter(array_keys($placed), fn (int $lane) => self::isFree($placed[$lane], $stretch['start'], $stretch['end'])));
            $preferred = array_values(array_intersect($preferredLanes[$stretch['appointment']->id] ?? [], $free));

            $lane = match (true) {
                $previous !== null && in_array($previous, $free, true) => $previous,
                $preferred !== [] => $preferred[0],
                $free !== [] => $free[0],
                default => count($placed),
            };

            $placed[$lane][] = [$stretch['start'], $stretch['end']];
            $lanes[$stretch['key']] = $lane;
        }

        $maxLanes = max($capacity, count($placed));

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
