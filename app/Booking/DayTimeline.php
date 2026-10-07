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
     * Flags every tappable free segment with 'fits' => true where the
     * service really belongs (review finding N1 of the coordinator's
     * agenda-service-filter review): $fittingMinutes alone says the
     * service fits *by capacity* at that start minute — true across every
     * lane at once, since capacity is not a lane concept (PRF-121) — but
     * highlighting every lane there regardless is visually misleading
     * when the lane's own appointment would collide with it (e.g. a
     * service starting at 11:00 "fits" at 11:00-12:00 by capacity even in
     * a lane holding an 11:40 appointment, because *some other* lane is
     * free the whole time).
     *
     * So for each candidate minute, only the lanes with no appointment
     * anywhere in [minute, minute + the length) are highlighted. If
     * none of them stays free the whole time (appointments staggered
     * across different lanes, each interrupting its own lane at a
     * different moment, never simultaneously enough to break capacity),
     * the first free lane at that minute (the same one the first-free-lane
     * algorithm itself would assign a new appointment to) is highlighted
     * anyway, same as a day with only one lane total (capacity 1) always
     * is, continuous or not — there is nowhere "safer" to point to.
     *
     * Every other free/tappable segment is explicitly set to 'fits' =>
     * false (never left unset): the view only needs to check the key, not
     * guess whether it is missing because this method never ran at all or
     * because it chose not to flag it.
     *
     * Deliberately presentation-only: never touches AvailabilityCalculator
     * or its rules, only how DayTimeline's own already-built lanes relate
     * to a capacity verdict decided elsewhere.
     *
     * @param  array{lanes: int, pieces: list<array>}  $timeline  a build() result
     *                                                            With waits in the chosen services (PRF-151), "free the whole time"
     *                                                            means free during their active stretches only: an appointment in the
     *                                                            lane during the wait does not collide.
     * @param  list<int>  $fittingMinutes  AvailabilityCalculator::fittingStartMinutes()'s result
     * @param  TimeProfile|int  $length  the chosen services' TimeProfile, or minutes with no waits
     * @return array{lanes: int, pieces: list<array>}
     */
    public static function markServiceFit(array $timeline, array $fittingMinutes, TimeProfile|int $length): array
    {
        $fitMinutes = array_flip($fittingMinutes);
        $activeOffsets = TimeProfile::of($length)->activeOffsets();

        foreach ($timeline['pieces'] as &$piece) {
            if ($piece['kind'] !== 'open') {
                continue;
            }

            // Every confirmed appointment's busy minutes, by lane, and
            // every free+tappable segment's lane(s), by start minute
            // (ascending, so the lowest lane index is always first — the
            // "first free lane" fallback needs exactly that order).
            $busyByLane = [];
            $freeLanesByMinute = [];

            foreach ($piece['segments'] as &$segment) {
                if ($segment['type'] === 'appointment') {
                    $busyByLane[$segment['lane']][] = [$segment['start'], $segment['end']];
                } elseif ($segment['type'] === 'free' && $segment['tappable']) {
                    $segment['fits'] = false;
                    $freeLanesByMinute[$segment['start']][] = $segment['lane'];
                }
            }
            unset($segment);

            foreach ($freeLanesByMinute as $minute => &$lanes) {
                sort($lanes);
            }
            unset($lanes);

            foreach ($freeLanesByMinute as $minute => $lanes) {
                if (! isset($fitMinutes[$minute])) {
                    continue;
                }

                $continuouslyFreeLanes = array_values(array_filter(
                    $lanes,
                    fn (int $lane) => ! collect($activeOffsets)->contains(
                        fn (array $offsets) => self::laneBusyDuring($busyByLane[$lane] ?? [], $minute + $offsets[0], $minute + $offsets[1])
                    )
                ));
                $highlight = array_flip($continuouslyFreeLanes !== [] ? $continuouslyFreeLanes : [$lanes[0]]);

                foreach ($piece['segments'] as &$segment) {
                    if ($segment['type'] === 'free' && $segment['tappable'] && $segment['start'] === $minute) {
                        $segment['fits'] = isset($highlight[$segment['lane']]);
                    }
                }
                unset($segment);
            }
        }

        return $timeline;
    }

    /**
     * Whether any of $busyIntervals (a lane's appointments, each
     * [start, end)) overlaps [start, end).
     *
     * @param  list<array{0: int, 1: int}>  $busyIntervals
     */
    private static function laneBusyDuring(array $busyIntervals, int $start, int $end): bool
    {
        foreach ($busyIntervals as [$busyStart, $busyEnd]) {
            if ($busyStart < $end && $busyEnd > $start) {
                return true;
            }
        }

        return false;
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

        // Lane assignment is global for the day: each active stretch of an
        // appointment (the whole appointment when it has no waits) has the
        // same lane wherever it is rendered.
        $laneAssignment = AppointmentLaneAssigner::assign($confirmed, $capacity);
        $maxLanes = $laneAssignment['maxLanes'];
        [$stretches, $waits] = self::stretchesAndWaits($laneAssignment, $dayStart);

        $pieces = [];

        if ($ranges->isEmpty()) {
            array_push($pieces, ...self::closedGap($gridStart, $gridEnd, 'cerrado', $appointmentIntervals, $maxLanes, $capacity, $stretches, $waits, $gridStart, $nowMinute));

            return ['lanes' => $maxLanes, 'pieces' => $pieces];
        }

        $blockIntervals = self::blockIntervals($dayStart, $blocks, $gridStart, $gridEnd);
        $openRanges = self::clippedRanges($ranges, $gridStart, $gridEnd);
        $cursor = $gridStart;

        foreach ($openRanges as $range) {
            if ($range['start'] > $cursor) {
                array_push($pieces, ...self::closedGap($cursor, $range['start'], 'fuera-horario', $appointmentIntervals, $maxLanes, $capacity, $stretches, $waits, $gridStart, $nowMinute));
            }

            foreach (self::splitByClosure($range, $blockIntervals, $appointmentIntervals, $capacity) as $sub) {
                $pieces[] = $sub['closed']
                    ? self::band('cierre', $sub['start'], $sub['end'], $gridStart)
                    : self::openPiece($sub['start'], $sub['end'], $gridStart, $maxLanes, $capacity, $stretches, $waits, $blockIntervals, $nowMinute);
            }

            $cursor = $range['end'];
        }

        if ($cursor < $gridEnd) {
            array_push($pieces, ...self::closedGap($cursor, $gridEnd, 'fuera-horario', $appointmentIntervals, $maxLanes, $capacity, $stretches, $waits, $gridStart, $nowMinute));
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
     * @param  list<array>  $stretches  stretchesAndWaits()
     * @param  list<array>  $waits  stretchesAndWaits()
     * @return list<array>
     */
    private static function closedGap(int $start, int $end, string $bandType, array $appointmentIntervals, int $maxLanes, int $capacity, array $stretches, array $waits, int $gridStart, int $nowMinute): array
    {
        $range = ['start' => $start, 'end' => $end];
        $syntheticBlock = ['start' => $start, 'end' => $end, 'reduction' => null];

        $pieces = [];

        foreach (self::splitByClosure($range, [$syntheticBlock], $appointmentIntervals, $capacity) as $sub) {
            $pieces[] = $sub['closed']
                ? self::band($bandType, $sub['start'], $sub['end'], $gridStart)
                : self::openPiece($sub['start'], $sub['end'], $gridStart, $maxLanes, $capacity, $stretches, $waits, [$syntheticBlock], $nowMinute);
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
     * @param  list<array>  $stretches  stretchesAndWaits()
     * @param  list<array>  $waits  stretchesAndWaits()
     * @param  list<array{start: int, end: int, reduction: int|null}>  $blockIntervals
     */
    private static function openPiece(int $start, int $end, int $gridStart, int $maxLanes, int $capacity, array $stretches, array $waits, array $blockIntervals, int $nowMinute): array
    {
        $lanes = [];

        for ($lane = 0; $lane < $maxLanes; $lane++) {
            $lanes[$lane] = self::laneSegments($start, $end, $lane, $gridStart, $capacity, $stretches, $waits, $blockIntervals, $nowMinute);
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
     * Each active stretch of the day's confirmed appointments in minutes
     * of the day, with its lane (AppointmentLaneAssigner), and each wait
     * between two stretches, with the lanes of the stretches on either
     * side of it: the lanes where "Espera · {clienta} hasta {hora}" is
     * shown on whatever is left free (PRF-159).
     *
     * @param  array{lanes: array<string, int>, overCapacity: array<string, bool>, stretches: list<array>}  $laneAssignment  AppointmentLaneAssigner::assign()
     * @return array{0: list<array{appointment: Appointment, lane: int, overCapacity: bool, start: int, end: int, part: int, parts: int, waitUntil: int|null}>, 1: list<array{lanes: list<int>, start: int, end: int, customer: string, until: string}>}
     */
    private static function stretchesAndWaits(array $laneAssignment, CarbonImmutable $dayStart): array
    {
        $byKey = [];

        foreach ($laneAssignment['stretches'] as $stretch) {
            $byKey[$stretch['key']] = $stretch;
        }

        $stretches = [];
        $waits = [];

        foreach ($laneAssignment['stretches'] as $stretch) {
            $next = $byKey[AppointmentLaneAssigner::key($stretch['appointment'], $stretch['index'] + 1)] ?? null;
            $end = self::minutesSinceDayStart($dayStart, $stretch['end']);
            $waitUntil = $next === null ? null : self::minutesSinceDayStart($dayStart, $next['start']);

            $stretches[] = [
                'appointment' => $stretch['appointment'],
                'lane' => $laneAssignment['lanes'][$stretch['key']],
                'overCapacity' => $laneAssignment['overCapacity'][$stretch['key']],
                'start' => self::minutesSinceDayStart($dayStart, $stretch['start']),
                'end' => $end,
                'part' => $stretch['index'] + 1,
                'parts' => $stretch['count'],
                'waitUntil' => $waitUntil,
            ];

            if ($next !== null) {
                $waits[] = [
                    'lanes' => array_values(array_unique([$laneAssignment['lanes'][$stretch['key']], $laneAssignment['lanes'][$next['key']]])),
                    'start' => $end,
                    'end' => $waitUntil,
                    'customer' => $stretch['appointment']->customer_name,
                    'until' => $next['start']->format('H:i'),
                ];
            }
        }

        return [$stretches, $waits];
    }

    /**
     * One lane's independent sequence of free/appointment/cierre-parcial
     * segments across one open piece, each carrying its own 'start'/'end'
     * (minutes since midnight) besides its pixel 'top'/'height', so the
     * caller can derive shared CSS-grid row boundaries across every lane.
     *
     * An appointment segment is one active stretch: 'part' of 'parts'
     * (1 of 1 without waits), 'stretchStart'/'stretchEnd' unclipped, and
     * 'waitUntil' when a wait follows it. A free segment overlapping a wait
     * in one of that wait's lanes carries 'wait' (customer and until), and
     * 'waitLabel' true only on the first segment of each continuous run.
     *
     * @param  list<array>  $stretches  stretchesAndWaits()
     * @param  list<array>  $waits  stretchesAndWaits()
     * @return list<array>
     */
    private static function laneSegments(int $start, int $end, int $lane, int $gridStart, int $capacity, array $stretches, array $waits, array $blockIntervals, int $nowMinute): array
    {
        $laneStretches = collect($stretches)
            ->filter(fn (array $stretch) => $stretch['lane'] === $lane && $stretch['start'] < $end && $stretch['end'] > $start)
            ->sortBy('start')
            ->values();

        $segments = [];
        $cursor = $start;

        foreach ($laneStretches as $stretch) {
            $aStart = max($stretch['start'], $start);
            $aEnd = min($stretch['end'], $end);

            if ($aStart > $cursor) {
                array_push($segments, ...self::gapSegments($cursor, $aStart, $lane, $gridStart, $blockIntervals, $capacity, $nowMinute));
            }

            $segments[] = [
                'type' => 'appointment',
                'start' => $aStart,
                'end' => $aEnd,
                'top' => self::pxFromMinutes($aStart - $gridStart),
                'height' => self::pxFromMinutes($aEnd - $gridStart) - self::pxFromMinutes($aStart - $gridStart),
                'appointment' => $stretch['appointment'],
                'overCapacity' => $stretch['overCapacity'],
                'part' => $stretch['part'],
                'parts' => $stretch['parts'],
                'stretchStart' => $stretch['start'],
                'stretchEnd' => $stretch['end'],
                'waitUntil' => $stretch['waitUntil'],
            ];

            $cursor = $aEnd;
        }

        if ($cursor < $end) {
            array_push($segments, ...self::gapSegments($cursor, $end, $lane, $gridStart, $blockIntervals, $capacity, $nowMinute));
        }

        $laneWaits = array_filter($waits, fn (array $wait) => in_array($lane, $wait['lanes'], true));
        $previousWait = null; // the wait marked on the previous segment of this lane, if any

        foreach ($segments as &$segment) {
            $wait = null;

            if ($segment['type'] === 'free') {
                foreach ($laneWaits as $candidate) {
                    if ($segment['start'] < $candidate['end'] && $segment['end'] > $candidate['start']) {
                        $wait = ['customer' => $candidate['customer'], 'until' => $candidate['until']];
                        break;
                    }
                }
            }

            if ($wait !== null) {
                $segment['wait'] = $wait;
                // A free run is split every half hour: the visible mark
                // goes once, on its first segment (the coordinator's
                // browser check); every segment keeps 'wait' for its own
                // aria-label and title.
                $segment['waitLabel'] = $wait !== $previousWait;
            }

            $previousWait = $wait;
        }
        unset($segment);

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
