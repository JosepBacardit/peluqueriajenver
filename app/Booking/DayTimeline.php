<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the Google-Calendar-style admin agenda grid (Día, and each column
 * of Semana): a shared hour axis across every day, fixed lanes (one per
 * unit of capacity, more if a day is over capacity), appointment blocks
 * assigned to lanes, and bands for closed/out-of-hours/closure periods.
 *
 * All positions are computed in plain integer minutes-since-midnight and
 * converted to pixels only at the end (pxFromMinutes()), so two adjacent
 * segments always share an exact pixel boundary — never a rounding gap or
 * overlap, which comparing round(a) and round(b) guarantees but
 * round(b - a) on its own would not.
 */
class DayTimeline
{
    /**
     * 88px/hour = 44px per half hour, the smallest free slot the grid
     * offers to tap (PRF-108, PRF-115).
     */
    public const PX_PER_HOUR = 88;

    /**
     * Minutes-of-day fallback when there is no opening-hours data at all
     * (should not happen past the booking migrations, but the grid must
     * still render something rather than divide by zero).
     */
    private const FALLBACK_START = 9 * 60;

    private const FALLBACK_END = 19 * 60;

    /**
     * The grid's start/end, in minutes since midnight: the earliest
     * opening and the latest closing of the whole week, rounded out to
     * the hour so every day/column shares the same clean hour lines
     * (PRF-108). Takes the already-loaded full week of OpeningHour rows
     * (no query here) so the caller can reuse the same load for the
     * day-specific ranges it also needs — never two queries for the same
     * table.
     *
     * @param  Collection<int, OpeningHour>  $allRanges  every weekday's ranges
     * @return array{start: int, end: int}
     */
    public static function weekBounds(Collection $allRanges): array
    {
        if ($allRanges->isEmpty()) {
            return ['start' => self::FALLBACK_START, 'end' => self::FALLBACK_END];
        }

        $open = $allRanges->min(fn (OpeningHour $hour) => $hour->opensAtMinutes());
        $close = $allRanges->max(fn (OpeningHour $hour) => $hour->closesAtMinutes());

        return [
            'start' => intdiv($open, 60) * 60,
            'end' => (int) ceil($close / 60) * 60,
        ];
    }

    /**
     * Pixels from the grid's start for a given number of minutes since it,
     * at 88px/hour. Always derive a segment's height from the difference
     * of two calls to this method (its start and its end), never from
     * rounding its duration on its own.
     */
    public static function pxFromMinutes(int $minutes): int
    {
        return (int) round($minutes * self::PX_PER_HOUR / 60);
    }

    /**
     * The "now" line's pixel offset from the grid's top (PRF-116), or null
     * when it should not be shown at all: $day is not today, or $now falls
     * outside [$gridStart, $gridEnd).
     */
    public static function nowLineTop(CarbonImmutable $day, int $gridStart, int $gridEnd, CarbonImmutable $now): ?int
    {
        if (! $day->isSameDay($now)) {
            return null;
        }

        $nowMinute = $now->hour * 60 + $now->minute;

        if ($nowMinute < $gridStart || $nowMinute >= $gridEnd) {
            return null;
        }

        return self::pxFromMinutes($nowMinute - $gridStart);
    }

    /**
     * Smallest free gap, in minutes, offered as its own tap target
     * (PRF-114) — independent of booking_settings.slot_interval_minutes.
     */
    private const MIN_TAPPABLE_MINUTES = 30;

    /**
     * Builds the renderable structure for one day's column: an ordered,
     * contiguous list of "pieces" from $gridStart to $gridEnd (minutes
     * since midnight) — each either a full-width band (bandType: cerrado,
     * fuera-horario or cierre) or an "open" piece with one segment list
     * per lane (free, appointment or cierre-parcial). No queries: only
     * the data the caller already loaded.
     *
     * @param  Collection<int, OpeningHour>  $ranges  this weekday's ranges
     * @param  Collection<int, Appointment>  $appointments  this day's, any status
     * @param  Collection<int, ScheduleBlock>  $blocks  overlapping this day, any capacity_reduction
     * @return array{lanes: int, pieces: list<array>}
     */
    public static function build(
        CarbonImmutable $day,
        int $gridStart,
        int $gridEnd,
        int $capacity,
        Collection $ranges,
        Collection $appointments,
        Collection $blocks,
    ): array {
        if ($ranges->isEmpty()) {
            return [
                'lanes' => $capacity,
                'pieces' => [self::band('cerrado', $gridStart, $gridEnd, $gridStart)],
            ];
        }

        $dayStart = $day->startOfDay();
        $openRanges = self::clippedRanges($ranges, $gridStart, $gridEnd);
        $blockIntervals = self::blockIntervals($dayStart, $blocks, $gridStart, $gridEnd);

        $confirmed = $appointments->filter(fn (Appointment $a) => $a->isConfirmed())->values();
        $appointmentIntervals = $confirmed->map(fn (Appointment $a) => [
            'start' => $dayStart->diffInMinutes($a->starts_at),
            'end' => $dayStart->diffInMinutes($a->ends_at),
        ])->all();

        // Lane assignment is global for the day: an appointment's lane is
        // the same wherever it is rendered (it only ever falls in one
        // open piece — appointments cannot be booked across a closure).
        $laneAssignment = AppointmentLaneAssigner::assign($confirmed, $capacity);
        $maxLanes = $laneAssignment['maxLanes'];

        $pieces = [];
        $cursor = $gridStart;

        foreach ($openRanges as $range) {
            if ($range['start'] > $cursor) {
                $pieces[] = self::band('fuera-horario', $cursor, $range['start'], $gridStart);
            }

            foreach (self::splitByClosure($range, $blockIntervals, $appointmentIntervals, $capacity) as $sub) {
                $pieces[] = $sub['closed']
                    ? self::band('cierre', $sub['start'], $sub['end'], $gridStart)
                    : self::openPiece($sub['start'], $sub['end'], $gridStart, $maxLanes, $capacity, $confirmed, $laneAssignment, $dayStart, $blockIntervals);
            }

            $cursor = $range['end'];
        }

        if ($cursor < $gridEnd) {
            $pieces[] = self::band('fuera-horario', $cursor, $gridEnd, $gridStart);
        }

        return ['lanes' => $maxLanes, 'pieces' => $pieces];
    }

    /**
     * @return list<array{start: int, end: int}> minute-of-day ranges, clipped to the grid and sorted
     */
    private static function clippedRanges(Collection $ranges, int $gridStart, int $gridEnd): array
    {
        return $ranges
            ->map(fn (OpeningHour $range) => ['start' => max($range->opensAtMinutes(), $gridStart), 'end' => min($range->closesAtMinutes(), $gridEnd)])
            ->filter(fn (array $range) => $range['start'] < $range['end'])
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * @return list<array{start: int, end: int, reduction: int|null}> minute-of-day intervals relative to $dayStart, clipped to the grid
     */
    private static function blockIntervals(CarbonImmutable $dayStart, Collection $blocks, int $gridStart, int $gridEnd): array
    {
        $dayEnd = $dayStart->addDay();

        return $blocks
            ->map(function (ScheduleBlock $block) use ($dayStart, $dayEnd, $gridStart, $gridEnd) {
                $start = max($block->starts_at, $dayStart);
                $end = min($block->ends_at, $dayEnd);

                return [
                    'start' => max($dayStart->diffInMinutes($start), $gridStart),
                    'end' => min($dayStart->diffInMinutes($end), $gridEnd),
                    'reduction' => $block->capacity_reduction,
                ];
            })
            ->filter(fn (array $block) => $block['start'] < $block['end'])
            ->values()
            ->all();
    }

    /**
     * The capacity left at a given minute once every active block's
     * reduction is subtracted (a full closure, capacity_reduction null,
     * counts as reducing it by the whole base capacity).
     *
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     */
    private static function effectiveCapacityAt(int $minute, array $blockIntervals, int $capacity): int
    {
        $reduction = 0;

        foreach ($blockIntervals as $block) {
            if ($block['start'] <= $minute && $minute < $block['end']) {
                $reduction += $block['reduction'] ?? $capacity;
            }
        }

        return $capacity - $reduction;
    }

    /**
     * Splits one opening range into closed ("cierre") and open elementary
     * sub-ranges. A sub-range only counts as closed when the effective
     * capacity there is 0 or less AND no confirmed appointment survives
     * inside it (PRF-022 allows a closure to be added over an existing
     * appointment without cancelling it) — when one does survive, the
     * whole sub-range stays "open" so it renders normally, with the other
     * lane(s) shaded by effectiveCapacityAt() inside openPiece().
     *
     * @param  array{start: int, end: int}  $range
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     * @param  list<array{start: int, end: int}>  $appointmentIntervals
     * @return list<array{start: int, end: int, closed: bool}>
     */
    private static function splitByClosure(array $range, array $blockIntervals, array $appointmentIntervals, int $capacity): array
    {
        $boundaries = [$range['start'], $range['end']];

        foreach ($blockIntervals as $block) {
            self::addBoundary($boundaries, $block['start'], $range);
            self::addBoundary($boundaries, $block['end'], $range);
        }

        foreach ($appointmentIntervals as $appointment) {
            self::addBoundary($boundaries, $appointment['start'], $range);
            self::addBoundary($boundaries, $appointment['end'], $range);
        }

        sort($boundaries);
        $boundaries = array_values(array_unique($boundaries));

        $pieces = [];

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            [$start, $end] = [$boundaries[$i], $boundaries[$i + 1]];
            $hasAppointment = collect($appointmentIntervals)->contains(fn ($a) => $a['start'] < $end && $a['end'] > $start);
            $closed = self::effectiveCapacityAt($start, $blockIntervals, $capacity) <= 0 && ! $hasAppointment;

            if ($pieces !== [] && end($pieces)['closed'] === $closed) {
                $pieces[array_key_last($pieces)]['end'] = $end;
            } else {
                $pieces[] = ['start' => $start, 'end' => $end, 'closed' => $closed];
            }
        }

        return $pieces;
    }

    private static function addBoundary(array &$boundaries, int $point, array $range): void
    {
        if ($point > $range['start'] && $point < $range['end']) {
            $boundaries[] = $point;
        }
    }

    /**
     * One "open" piece: $maxLanes parallel lanes, each an independent
     * vertical sequence of free/appointment/cierre-parcial segments whose
     * heights sum exactly to the piece's duration (normal document flow,
     * no absolute positioning needed — the shared hour axis stays aligned
     * automatically as long as every segment's height is exact).
     *
     * @param  Collection<int, Appointment>  $confirmed
     * @param  array{lanes: array<int, int>, maxLanes: int, overCapacity: array<int, bool>}  $laneAssignment
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     */
    private static function openPiece(int $start, int $end, int $gridStart, int $maxLanes, int $capacity, Collection $confirmed, array $laneAssignment, CarbonImmutable $dayStart, array $blockIntervals): array
    {
        $laneSegments = [];

        for ($lane = 0; $lane < $maxLanes; $lane++) {
            $laneAppointments = $confirmed
                ->filter(fn (Appointment $a) => ($laneAssignment['lanes'][$a->id] ?? null) === $lane)
                ->filter(function (Appointment $a) use ($dayStart, $start, $end) {
                    $aStart = $dayStart->diffInMinutes($a->starts_at);
                    $aEnd = $dayStart->diffInMinutes($a->ends_at);

                    return $aStart < $end && $aEnd > $start;
                })
                ->sortBy('starts_at')
                ->values();

            $segments = [];
            $cursor = $start;

            foreach ($laneAppointments as $appointment) {
                $aStart = max($dayStart->diffInMinutes($appointment->starts_at), $start);
                $aEnd = min($dayStart->diffInMinutes($appointment->ends_at), $end);

                if ($aStart > $cursor) {
                    array_push($segments, ...self::gapSegments($cursor, $aStart, $lane, $gridStart, $blockIntervals, $capacity));
                }

                $segments[] = [
                    'type' => 'appointment',
                    'top' => self::pxFromMinutes($aStart - $gridStart),
                    'height' => self::pxFromMinutes($aEnd - $gridStart) - self::pxFromMinutes($aStart - $gridStart),
                    'appointment' => $appointment,
                    'overCapacity' => $laneAssignment['overCapacity'][$appointment->id] ?? false,
                ];

                $cursor = $aEnd;
            }

            if ($cursor < $end) {
                array_push($segments, ...self::gapSegments($cursor, $end, $lane, $gridStart, $blockIntervals, $capacity));
            }

            $laneSegments[$lane] = $segments;
        }

        return [
            'kind' => 'open',
            'top' => self::pxFromMinutes($start - $gridStart),
            'height' => self::pxFromMinutes($end - $gridStart) - self::pxFromMinutes($start - $gridStart),
            'laneSegments' => $laneSegments,
        ];
    }

    /**
     * Splits one lane's free gap by partial-closure boundaries into
     * free/cierre-parcial segments (PRF-113), merging adjacent segments of
     * the same type, and flags each free one as tappable only once it is
     * at least 30 minutes long (PRF-114).
     *
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     * @return list<array>
     */
    private static function gapSegments(int $start, int $end, int $lane, int $gridStart, array $blockIntervals, int $capacity): array
    {
        $boundaries = [$start, $end];
        $range = ['start' => $start, 'end' => $end];

        foreach ($blockIntervals as $block) {
            self::addBoundary($boundaries, $block['start'], $range);
            self::addBoundary($boundaries, $block['end'], $range);
        }

        sort($boundaries);
        $boundaries = array_values(array_unique($boundaries));

        $raw = [];

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            [$segStart, $segEnd] = [$boundaries[$i], $boundaries[$i + 1]];
            $type = $lane >= self::effectiveCapacityAt($segStart, $blockIntervals, $capacity) ? 'cierre-parcial' : 'free';

            if ($raw !== [] && end($raw)['type'] === $type) {
                $raw[array_key_last($raw)]['end'] = $segEnd;
            } else {
                $raw[] = ['type' => $type, 'start' => $segStart, 'end' => $segEnd];
            }
        }

        return array_map(fn (array $segment) => [
            'type' => $segment['type'],
            'top' => self::pxFromMinutes($segment['start'] - $gridStart),
            'height' => self::pxFromMinutes($segment['end'] - $gridStart) - self::pxFromMinutes($segment['start'] - $gridStart),
            'tappable' => $segment['type'] === 'free' && ($segment['end'] - $segment['start']) >= self::MIN_TAPPABLE_MINUTES,
            'startMinute' => $segment['start'],
        ], $raw);
    }

    private static function band(string $type, int $start, int $end, int $gridStart): array
    {
        return [
            'kind' => 'band',
            'bandType' => $type,
            'top' => self::pxFromMinutes($start - $gridStart),
            'height' => self::pxFromMinutes($end - $gridStart) - self::pxFromMinutes($start - $gridStart),
        ];
    }
}
