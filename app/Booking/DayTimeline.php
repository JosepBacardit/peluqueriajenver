<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
     * Extends $gridStart/$gridEnd (review finding H1) so a confirmed
     * appointment forced outside the normal opening hours ("Guardar
     * igualmente" skips every hour/capacity check,
     * RescheduleAppointment::handle() with ignoreHoursAndCapacity: true)
     * is never left occupying an invisible lane: it still counts towards
     * AppointmentLaneAssigner's global assignment for the day, so it must
     * also get a visible place in the grid. Rounded out to the hour, same
     * convention as weekBounds(), so the axis stays clean.
     *
     * Takes any Collection of appointments — a single day's (vista Día) or
     * a whole week's (vista Semana, whose grid bounds are shared across
     * all 7 columns): each appointment supplies its own anchor day via its
     * own starts_at, so this never confuses one day's clock minutes with
     * another's.
     *
     * @param  Collection<int, Appointment>  $appointments  any status; only confirmed ones count
     */
    public static function extendBounds(int $gridStart, int $gridEnd, Collection $appointments): array
    {
        foreach ($appointments as $appointment) {
            if (! $appointment->isConfirmed()) {
                continue;
            }

            $dayStart = $appointment->starts_at->startOfDay();
            $start = self::minutesSinceDayStart($dayStart, $appointment->starts_at);
            $end = self::minutesSinceDayStart($dayStart, $appointment->ends_at);

            $gridStart = min($gridStart, intdiv($start, 60) * 60);
            $gridEnd = max($gridEnd, (int) ceil($end / 60) * 60);
        }

        return ['start' => $gridStart, 'end' => $gridEnd];
    }

    /**
     * Every tappable free segment's start minute, across every lane of
     * every open piece, deduplicated (a service filter's candidate times,
     * PRF-123): the agenda passes this straight to
     * AvailabilityCalculator::fittingStartMinutes(), so the two never
     * disagree about which times are even offered as tap targets. A pure
     * data walk over an already-built timeline — no new computation.
     *
     * @param  array{lanes: int, pieces: list<array>}  $timeline  a build() result
     * @return list<int>
     */
    public static function tappableFreeMinutes(array $timeline): array
    {
        $minutes = [];

        foreach ($timeline['pieces'] as $piece) {
            if ($piece['kind'] !== 'open') {
                continue;
            }

            foreach ($piece['segments'] as $segment) {
                if ($segment['type'] === 'free' && $segment['tappable']) {
                    $minutes[] = $segment['start'];
                }
            }
        }

        return array_values(array_unique($minutes));
    }

    /**
     * Flags every tappable free segment whose start is one of
     * $fittingMinutes with 'fits' => true (PRF-123, PRF-124: the "Cabe"
     * highlight) — every other segment is left untouched, so the view
     * treats a missing 'fits' key the same as false. Builds its own lookup
     * from $fittingMinutes once, rather than a linear search per segment.
     *
     * @param  array{lanes: int, pieces: list<array>}  $timeline  a build() result
     * @param  list<int>  $fittingMinutes  AvailabilityCalculator::fittingStartMinutes()'s result
     * @return array{lanes: int, pieces: list<array>}
     */
    public static function markServiceFit(array $timeline, array $fittingMinutes): array
    {
        $fits = array_flip($fittingMinutes);

        foreach ($timeline['pieces'] as &$piece) {
            if ($piece['kind'] !== 'open') {
                continue;
            }

            foreach ($piece['segments'] as &$segment) {
                if ($segment['type'] === 'free' && $segment['tappable']) {
                    $segment['fits'] = isset($fits[$segment['start']]);
                }
            }
        }

        return $timeline;
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
     * Minutes since $dayStart's midnight, using wall-clock hour/minute —
     * not Carbon's diffInMinutes(), which measures real elapsed time and
     * drifts by an hour on the two Sundays a year Europe/Madrid changes
     * its clock (review finding L1). $moment may be on a later calendar
     * day than $dayStart (a block or appointment clamped to next
     * midnight): each full day in between simply adds 1440, computed from
     * the date parts alone (via a UTC date diff) so it is never thrown
     * off by a DST transition the way a real-elapsed-time diff would be.
     */
    private static function minutesSinceDayStart(CarbonImmutable $dayStart, CarbonInterface $moment): int
    {
        $days = self::daysBetweenDates($dayStart, $moment);

        return $days * 1440 + $moment->hour * 60 + $moment->minute;
    }

    /**
     * Calendar days between two moments' dates alone (never their real
     * elapsed time, which a DST transition can shift by an hour either
     * side of a whole day). Both dates are parsed in UTC, where there is
     * no DST, so the difference is always exact.
     */
    private static function daysBetweenDates(CarbonInterface $from, CarbonInterface $to): int
    {
        $fromUtc = CarbonImmutable::createFromFormat('!Y-m-d', $from->format('Y-m-d'), 'UTC');
        $toUtc = CarbonImmutable::createFromFormat('!Y-m-d', $to->format('Y-m-d'), 'UTC');

        return (int) $fromUtc->diffInDays($toUtc, false);
    }

    /**
     * Builds the renderable structure for one day's column: an ordered,
     * contiguous list of "pieces" from $gridStart to $gridEnd (minutes
     * since midnight) — each either a full-width band (bandType: cerrado,
     * fuera-horario or cierre) or an "open" piece with one CSS-grid row
     * per distinct boundary minute and one column per lane, its segments
     * (free, appointment or cierre-parcial) placed by explicit grid-row/
     * grid-column (PRF-119: the DOM order of 'segments' is chronological
     * across every lane, not grouped by lane first). No queries: only the
     * data the caller already loaded.
     *
     * A stretch with no real opening-hours tramo — the whole day (review
     * finding M1, PRF-105) or a gap inside the shared grid but outside
     * this day's own hours (review finding H1, reachable only via
     * "Guardar igualmente") — behaves like a full closure: zero effective
     * capacity, unless a confirmed appointment was forced into it, in
     * which case it survives in its lane exactly like a real ScheduleBlock
     * closure does (PRF-022), with the rest of the stretch still banded.
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
        CarbonImmutable $now,
    ): array {
        $dayStart = $day->startOfDay();
        $nowMinute = self::minutesSinceDayStart($dayStart, $now);

        $confirmed = $appointments->filter(fn (Appointment $a) => $a->isConfirmed())->values();
        $appointmentIntervals = $confirmed->map(fn (Appointment $a) => [
            'start' => self::minutesSinceDayStart($dayStart, $a->starts_at),
            'end' => self::minutesSinceDayStart($dayStart, $a->ends_at),
        ])->all();

        // Lane assignment is global for the day: an appointment's lane is
        // the same wherever it is rendered (it only ever falls in one
        // open piece — appointments cannot be booked across a closure).
        $laneAssignment = AppointmentLaneAssigner::assign($confirmed, $capacity);
        $maxLanes = $laneAssignment['maxLanes'];

        $pieces = [];

        if ($ranges->isEmpty()) {
            array_push($pieces, ...self::closedGap($gridStart, $gridEnd, 'cerrado', $appointmentIntervals, $maxLanes, $capacity, $confirmed, $laneAssignment, $dayStart, $gridStart, $nowMinute));

            return ['lanes' => $maxLanes, 'pieces' => $pieces];
        }

        $blockIntervals = self::blockIntervals($dayStart, $blocks, $gridStart, $gridEnd);
        $openRanges = self::clippedRanges($ranges, $gridStart, $gridEnd);
        $cursor = $gridStart;

        foreach ($openRanges as $range) {
            if ($range['start'] > $cursor) {
                array_push($pieces, ...self::closedGap($cursor, $range['start'], 'fuera-horario', $appointmentIntervals, $maxLanes, $capacity, $confirmed, $laneAssignment, $dayStart, $gridStart, $nowMinute));
            }

            foreach (self::splitByClosure($range, $blockIntervals, $appointmentIntervals, $capacity) as $sub) {
                $pieces[] = $sub['closed']
                    ? self::band('cierre', $sub['start'], $sub['end'], $gridStart)
                    : self::openPiece($sub['start'], $sub['end'], $gridStart, $maxLanes, $capacity, $confirmed, $laneAssignment, $dayStart, $blockIntervals, $nowMinute);
            }

            $cursor = $range['end'];
        }

        if ($cursor < $gridEnd) {
            array_push($pieces, ...self::closedGap($cursor, $gridEnd, 'fuera-horario', $appointmentIntervals, $maxLanes, $capacity, $confirmed, $laneAssignment, $dayStart, $gridStart, $nowMinute));
        }

        return ['lanes' => $maxLanes, 'pieces' => $pieces];
    }

    /**
     * A stretch of the grid with no real opening-hours tramo (review
     * findings H1 and M1): treated as a synthetic full closure (zero
     * effective capacity throughout) via the same splitByClosure()
     * survivor logic a real ScheduleBlock closure already uses, so a
     * confirmed appointment forced into it still gets its lane instead of
     * vanishing behind a single blind band. $bandType is "cerrado" for a
     * weekday with no schedule at all, "fuera-horario" for a gap inside
     * the shared grid but outside this day's own tramo.
     *
     * @param  list<array{start: int, end: int}>  $appointmentIntervals
     * @param  array{lanes: array<int, int>, maxLanes: int, overCapacity: array<int, bool>}  $laneAssignment
     * @return list<array>
     */
    private static function closedGap(int $start, int $end, string $bandType, array $appointmentIntervals, int $maxLanes, int $capacity, Collection $confirmed, array $laneAssignment, CarbonImmutable $dayStart, int $gridStart, int $nowMinute): array
    {
        $range = ['start' => $start, 'end' => $end];
        $syntheticBlock = ['start' => $start, 'end' => $end, 'reduction' => null];

        $pieces = [];

        foreach (self::splitByClosure($range, [$syntheticBlock], $appointmentIntervals, $capacity) as $sub) {
            $pieces[] = $sub['closed']
                ? self::band($bandType, $sub['start'], $sub['end'], $gridStart)
                : self::openPiece($sub['start'], $sub['end'], $gridStart, $maxLanes, $capacity, $confirmed, $laneAssignment, $dayStart, [$syntheticBlock], $nowMinute);
        }

        return $pieces;
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
                    'start' => max(self::minutesSinceDayStart($dayStart, $start), $gridStart),
                    'end' => min(self::minutesSinceDayStart($dayStart, $end), $gridEnd),
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
     * One "open" piece: $maxLanes lanes placed as CSS-grid columns, their
     * segments as CSS-grid rows sized in minutes via "fr" units (real-
     * number division, so — like pxFromMinutes() — proportions never
     * drift from integer rounding), emitted in one globally-chronological
     * list (review finding L4) instead of grouped lane-by-lane, so a
     * keyboard/screen-reader user tabs through the day the way it
     * actually happened.
     *
     * @param  Collection<int, Appointment>  $confirmed
     * @param  array{lanes: array<int, int>, maxLanes: int, overCapacity: array<int, bool>}  $laneAssignment
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     */
    private static function openPiece(int $start, int $end, int $gridStart, int $maxLanes, int $capacity, Collection $confirmed, array $laneAssignment, CarbonImmutable $dayStart, array $blockIntervals, int $nowMinute): array
    {
        $lanes = [];

        for ($lane = 0; $lane < $maxLanes; $lane++) {
            $lanes[$lane] = self::laneSegments($start, $end, $lane, $gridStart, $capacity, $confirmed, $laneAssignment, $dayStart, $blockIntervals, $nowMinute);
        }

        $boundaries = [$start, $end];

        foreach ($lanes as $segments) {
            foreach ($segments as $segment) {
                $boundaries[] = $segment['start'];
                $boundaries[] = $segment['end'];
            }
        }

        sort($boundaries);
        $boundaries = array_values(array_unique($boundaries));

        $rowLineOf = [];

        foreach ($boundaries as $i => $minute) {
            $rowLineOf[$minute] = $i + 1;
        }

        $rowSizes = [];

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $rowSizes[] = ($boundaries[$i + 1] - $boundaries[$i]).'fr';
        }

        $chronological = [];

        foreach ($lanes as $lane => $segments) {
            foreach ($segments as $segment) {
                $segment['lane'] = $lane;
                $segment['gridRowStart'] = $rowLineOf[$segment['start']];
                $segment['gridRowEnd'] = $rowLineOf[$segment['end']];
                $chronological[] = $segment;
            }
        }

        usort($chronological, fn (array $a, array $b) => $a['start'] <=> $b['start'] ?: $a['lane'] <=> $b['lane']);

        return [
            'kind' => 'open',
            'top' => self::pxFromMinutes($start - $gridStart),
            'height' => self::pxFromMinutes($end - $gridStart) - self::pxFromMinutes($start - $gridStart),
            'maxLanes' => $maxLanes,
            'gridTemplateRows' => implode(' ', $rowSizes),
            'segments' => $chronological,
        ];
    }

    /**
     * One lane's independent sequence of free/appointment/cierre-parcial
     * segments across one open piece, each carrying its own 'start'/'end'
     * (minutes since midnight) besides its pixel 'top'/'height', so the
     * caller can derive shared CSS-grid row boundaries across every lane.
     *
     * @return list<array>
     */
    private static function laneSegments(int $start, int $end, int $lane, int $gridStart, int $capacity, Collection $confirmed, array $laneAssignment, CarbonImmutable $dayStart, array $blockIntervals, int $nowMinute): array
    {
        $laneAppointments = $confirmed
            ->filter(fn (Appointment $a) => ($laneAssignment['lanes'][$a->id] ?? null) === $lane)
            ->filter(function (Appointment $a) use ($dayStart, $start, $end) {
                $aStart = self::minutesSinceDayStart($dayStart, $a->starts_at);
                $aEnd = self::minutesSinceDayStart($dayStart, $a->ends_at);

                return $aStart < $end && $aEnd > $start;
            })
            ->sortBy('starts_at')
            ->values();

        $segments = [];
        $cursor = $start;

        foreach ($laneAppointments as $appointment) {
            $aStart = max(self::minutesSinceDayStart($dayStart, $appointment->starts_at), $start);
            $aEnd = min(self::minutesSinceDayStart($dayStart, $appointment->ends_at), $end);

            if ($aStart > $cursor) {
                array_push($segments, ...self::gapSegments($cursor, $aStart, $lane, $gridStart, $blockIntervals, $capacity, $nowMinute));
            }

            $segments[] = [
                'type' => 'appointment',
                'start' => $aStart,
                'end' => $aEnd,
                'top' => self::pxFromMinutes($aStart - $gridStart),
                'height' => self::pxFromMinutes($aEnd - $gridStart) - self::pxFromMinutes($aStart - $gridStart),
                'appointment' => $appointment,
                'overCapacity' => $laneAssignment['overCapacity'][$appointment->id] ?? false,
            ];

            $cursor = $aEnd;
        }

        if ($cursor < $end) {
            array_push($segments, ...self::gapSegments($cursor, $end, $lane, $gridStart, $blockIntervals, $capacity, $nowMinute));
        }

        return $segments;
    }

    /**
     * Splits one lane's free gap by partial-closure boundaries and by
     * every half-hour mark (review finding N1), merging only adjacent
     * cierre-parcial pieces back together (they are not tap targets, so
     * splitting them serves no purpose). A free run that does not start on
     * a clean half hour (e.g. right after an appointment ending at 11:55)
     * gets a short non-tappable leading filler up to the next :00/:30 mark
     * — chosen over making that odd time its own tap target, so every
     * link in the grid always lands on a round half hour, consistent with
     * how the booking form itself steps (PRF-114). A free half-hour is
     * only tappable once it has not already passed (review finding L2):
     * its start must be at or after $nowMinute (today before "ahora", or
     * any past day, naturally compares false; any future day compares
     * true for everything, since $nowMinute is then far outside the
     * day's own 0..1440 range).
     *
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     * @return list<array>
     */
    private static function gapSegments(int $start, int $end, int $lane, int $gridStart, array $blockIntervals, int $capacity, int $nowMinute): array
    {
        $boundaries = [$start, $end];
        $range = ['start' => $start, 'end' => $end];

        foreach ($blockIntervals as $block) {
            self::addBoundary($boundaries, $block['start'], $range);
            self::addBoundary($boundaries, $block['end'], $range);
        }

        for ($mark = (intdiv($start, 30) + 1) * 30; $mark < $end; $mark += 30) {
            self::addBoundary($boundaries, $mark, $range);
        }

        sort($boundaries);
        $boundaries = array_values(array_unique($boundaries));

        $raw = [];

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            [$segStart, $segEnd] = [$boundaries[$i], $boundaries[$i + 1]];
            $type = $lane >= self::effectiveCapacityAt($segStart, $blockIntervals, $capacity) ? 'cierre-parcial' : 'free';

            if ($raw !== [] && $type === 'cierre-parcial' && end($raw)['type'] === 'cierre-parcial') {
                $raw[array_key_last($raw)]['end'] = $segEnd;
            } else {
                $raw[] = ['type' => $type, 'start' => $segStart, 'end' => $segEnd];
            }
        }

        return array_map(fn (array $segment) => [
            'type' => $segment['type'],
            'start' => $segment['start'],
            'end' => $segment['end'],
            'top' => self::pxFromMinutes($segment['start'] - $gridStart),
            'height' => self::pxFromMinutes($segment['end'] - $gridStart) - self::pxFromMinutes($segment['start'] - $gridStart),
            'tappable' => $segment['type'] === 'free'
                && $segment['start'] % self::MIN_TAPPABLE_MINUTES === 0
                && ($segment['end'] - $segment['start']) === self::MIN_TAPPABLE_MINUTES
                && $segment['start'] >= $nowMinute,
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
