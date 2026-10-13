<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Hairdresser;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides which start times can be booked for a given length: a
 * TimeProfile (its duration and the waits inside it), or a plain number of
 * minutes for one with no waits.
 *
 * A start time is available when:
 *  1. the whole appointment, waits included, fits inside one opening range
 *     of that day;
 *  2. at no moment of the appointment's active stretches (outside its
 *     waits) do the active stretches of the confirmed appointments reach
 *     the hairdressers available then, and at no moment of the whole
 *     appointment, waits included, is no hairdresser available (a full
 *     closure: the customer cannot wait in a closed salon). An
 *     appointment waiting takes no place. The capacity is the number of
 *     active hairdressers (PRF-162); see capacityProblem() for how
 *     appointments with and without a hairdresser are counted (PRF-165).
 *     An appointment with no hairdresser can be done by any free one, even
 *     a different one after a wait;
 *  3. public bookings only: it is on the slot-interval grid counted from
 *     the start of the range, not earlier than now + minimum notice, and
 *     its day is not later than today + maximum advance.
 *
 * Settings, schedule and hairdressers are read on every call (never
 * cached on the instance), so a re-check inside the booking transaction
 * always sees the current values.
 *
 * $hairdresserId, wherever it appears, is the hairdresser the new
 * appointment is for, or null for "any hairdresser".
 */
class AvailabilityCalculator
{
    /**
     * The last shareBase() worked out, by the identity of its context.
     *
     * @var array<int, array{blocks: Collection<int, ScheduleBlock>, base: array}>
     */
    private array $shareBases = [];

    /**
     * Start times offered on the public booking page for one day.
     *
     * @return list<CarbonImmutable>
     */
    public function availableStartTimes(TimeProfile|int $length, CarbonImmutable $day, CarbonImmutable $now, ?int $hairdresserId = null): array
    {
        $day = $day->startOfDay();

        return $this->startTimesFor(
            TimeProfile::of($length), $day, $now,
            BookingSetting::current(),
            $this->rangesFor($day),
            $this->loadOccupation($day, $day->addDay()),
            $hairdresserId,
        );
    }

    /**
     * @param  Collection<int, OpeningHour>  $ranges  the day's opening ranges
     * @param  array{busy: list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>, blocks: Collection<int, ScheduleBlock>, staff: list<int>}  $context  occupation covering at least the day
     * @return list<CarbonImmutable>
     */
    private function startTimesFor(TimeProfile $profile, CarbonImmutable $day, CarbonImmutable $now, BookingSetting $settings, Collection $ranges, array $context, ?int $hairdresserId): array
    {
        if (! $this->isWithinBookingWindow($day, $now, $settings)) {
            return [];
        }

        $earliest = $now->addMinutes($settings->min_notice_minutes);
        $times = [];

        foreach ($ranges as $range) {
            for ($minute = $range->opensAtMinutes(); $minute + $profile->durationMinutes <= $range->closesAtMinutes(); $minute += $settings->slot_interval_minutes) {
                $start = $this->atMinute($day, $minute);

                if ($start->lt($earliest)) {
                    continue;
                }

                if ($this->hasCapacity($profile, $start, $context, $hairdresserId)) {
                    $times[] = $start;
                }
            }
        }

        return $times;
    }

    /**
     * Whether one specific start time can be booked. Admin bookings
     * ($applyPublicRules = false) skip rule 3 but still cannot start in
     * the past.
     *
     * $excludeAppointmentId is the appointment being moved: it must not
     * count against its own new time, or it could never be moved to a
     * time overlapping the one it holds now (e.g. 15 minutes earlier on a
     * full day).
     */
    public function isAvailable(TimeProfile|int $length, CarbonImmutable $start, CarbonImmutable $now, bool $applyPublicRules, ?int $excludeAppointmentId = null, ?int $hairdresserId = null): bool
    {
        return $this->unavailabilityReason($length, $start, $now, $applyPublicRules, $excludeAppointmentId, $hairdresserId) === null;
    }

    /**
     * Why one specific start time cannot be booked (same rules and
     * parameters as isAvailable()), or null when it can. The panel uses it
     * to tell the salon what is wrong with the time it chose.
     */
    public function unavailabilityReason(TimeProfile|int $length, CarbonImmutable $start, CarbonImmutable $now, bool $applyPublicRules, ?int $excludeAppointmentId = null, ?int $hairdresserId = null): ?UnavailabilityReason
    {
        $profile = TimeProfile::of($length);
        $durationMinutes = $profile->durationMinutes;
        $settings = BookingSetting::current();
        $day = $start->startOfDay();
        $startMinute = $start->hour * 60 + $start->minute;

        if ($start->lt($now)) {
            return UnavailabilityReason::InThePast;
        }

        // Not a whole minute: never sent by the forms.
        if ($start->second !== 0) {
            return UnavailabilityReason::OutsidePublicRules;
        }

        $range = $this->rangeContaining($this->rangesFor($day), $startMinute, $durationMinutes);

        if ($range === null) {
            return UnavailabilityReason::OutsideOpeningHours;
        }

        if ($applyPublicRules) {
            $onGrid = ($startMinute - $range->opensAtMinutes()) % $settings->slot_interval_minutes === 0;

            if (! $onGrid
                || $start->lt($now->addMinutes($settings->min_notice_minutes))
                || ! $this->isWithinBookingWindow($day, $now, $settings)) {
                return UnavailabilityReason::OutsidePublicRules;
            }
        }

        // The whole day: sharing it out among the hairdressers (PRF-165)
        // can depend on appointments that do not overlap this one.
        return $this->capacityProblem($profile, $start, $this->loadOccupation($day, $day->addDay(), $excludeAppointmentId), $hairdresserId);
    }

    /**
     * Which of the candidate start times a service of $length (its
     * TimeProfile, or minutes with no waits) fits into on $day, by rules 1
     * and 2 only (an opening range holds the whole service, and the
     * hairdressers available, after the blocks and full closures, are
     * never all taken by the active stretches of the confirmed
     * appointments):
     * the same rules and the same capacity check (capacityProblem()) as
     * isAvailable(..., applyPublicRules: false), for $hairdresserId (null:
     * any hairdresser). Rule 3 (slot interval, minimum notice, booking
     * window) never applies, and neither does isAvailable()'s "not in the
     * past": the agenda decides what to do with past times. A test checks
     * it agrees with isAvailable() on many random days.
     *
     * It runs no query: everything comes from the context the agenda
     * already loads for DayTimeline::build(), so the agenda's service
     * filter costs nothing per day, lane or half hour. Usage, in
     * AgendaController::dayData() (and per day in weekData(), with that
     * day's $dayRanges, $dayAppointments and $dayBlocks):
     *
     *     $fitting = $calculator->fittingStartMinutes(
     *         TimeProfile::fromServices($services),
     *         $candidateMinutes, // e.g. the 'start' of the timeline's tappable free segments
     *         $day, $dayRanges, $appointments, $blocks, $staff,
     *     );
     *     $fits = array_flip($fitting); // isset($fits[$segment['start']])
     *
     * Candidates are minutes since midnight on the wall clock, the unit
     * DayTimeline uses, so they stay right on the days the clocks change.
     *
     * @param  list<int>  $candidateMinutes  candidate start times, minutes since midnight
     * @param  Collection<int, OpeningHour>  $ranges  $day's opening ranges
     * @param  Collection<int, Appointment>  $appointments  every appointment that can overlap $day, any status (only confirmed ones count); those starting on $day are enough, since none crosses midnight
     * @param  Collection<int, ScheduleBlock>  $blocks  the blocks overlapping $day
     * @param  list<int>|int  $staff  the active hairdressers' ids (activeStaff()), or only how many there are (then no appointment or block can name one of them)
     * @return list<int> the candidates the service fits into, in the given order
     */
    public function fittingStartMinutes(TimeProfile|int $length, array $candidateMinutes, CarbonImmutable $day, Collection $ranges, Collection $appointments, Collection $blocks, array|int $staff, ?int $hairdresserId = null): array
    {
        $profile = TimeProfile::of($length);
        $day = $day->startOfDay();
        $context = [
            'busy' => self::busyIntervals($appointments->filter(fn (Appointment $appointment) => $appointment->isConfirmed())),
            'blocks' => $blocks,
            'staff' => is_int($staff) ? self::anonymousStaff($staff) : array_values($staff),
        ];

        return array_values(array_filter($candidateMinutes, function (int $minute) use ($profile, $day, $ranges, $context, $hairdresserId): bool {
            if ($this->rangeContaining($ranges, $minute, $profile->durationMinutes) === null) {
                return false;
            }

            return $this->hasCapacity($profile, $this->atMinute($day, $minute), $context, $hairdresserId);
        }));
    }

    /**
     * The active hairdressers' ids, in the salon's order: the capacity is
     * how many there are (PRF-162).
     *
     * @return list<int>
     */
    public static function activeStaff(): array
    {
        return Hairdresser::query()->active()->ordered()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * $count stand-ins for hairdressers that no appointment or block can
     * name (negative ids), for callers that only know how many there are.
     *
     * @return list<int>
     */
    private static function anonymousStaff(int $count): array
    {
        return $count < 1 ? [] : range(-1, -$count);
    }

    /**
     * Days between $from and $to (inclusive) with at least one public
     * start time.
     *
     * @return list<string> dates as Y-m-d
     */
    public function daysWithAvailability(TimeProfile|int $length, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now, ?int $hairdresserId = null): array
    {
        // Loaded once for the whole range (a handful of queries for a month
        // view instead of four per day).
        $settings = BookingSetting::current();
        $rangesByWeekday = OpeningHour::query()->orderBy('opens_at')->get()->groupBy('weekday');
        $context = $this->loadOccupation($from->startOfDay(), $to->startOfDay()->addDay());
        $profile = TimeProfile::of($length);
        $days = [];

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $ranges = $rangesByWeekday->get($day->isoWeekday(), collect());

            // Only that day's part of the occupation: every start time of
            // the day is checked against it, so it pays to keep it small.
            $nextDay = $day->addDay();
            $dayContext = [
                'busy' => array_values(array_filter($context['busy'], fn (array $interval) => $interval[0]->lt($nextDay) && $interval[1]->gt($day))),
                'blocks' => $context['blocks']->filter(fn (ScheduleBlock $block) => $block->starts_at->lt($nextDay) && $block->ends_at->gt($day))->values(),
                'staff' => $context['staff'],
            ];

            if ($this->startTimesFor($profile, $day, $now, $settings, $ranges, $dayContext, $hairdresserId) !== []) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    public function lastBookableDay(CarbonImmutable $now): CarbonImmutable
    {
        return $now->startOfDay()->addDays(BookingSetting::current()->max_advance_days);
    }

    private function isWithinBookingWindow(CarbonImmutable $day, CarbonImmutable $now, BookingSetting $settings): bool
    {
        return $day->gte($now->startOfDay())
            && $day->lte($now->startOfDay()->addDays($settings->max_advance_days));
    }

    /**
     * @return Collection<int, OpeningHour>
     */
    private function rangesFor(CarbonImmutable $day): Collection
    {
        return OpeningHour::query()
            ->where('weekday', $day->isoWeekday())
            ->orderBy('opens_at')
            ->get();
    }

    /**
     * Rule 1: the opening range that holds the whole appointment, if any.
     *
     * @param  Collection<int, OpeningHour>  $ranges
     */
    private function rangeContaining(Collection $ranges, int $startMinute, int $durationMinutes): ?OpeningHour
    {
        return $ranges->first(
            fn (OpeningHour $range) => $startMinute >= $range->opensAtMinutes()
                && $startMinute + $durationMinutes <= $range->closesAtMinutes()
        );
    }

    /**
     * Built from the wall-clock time (not by adding minutes to midnight),
     * so times stay right on the days the clocks change.
     */
    private function atMinute(CarbonImmutable $day, int $minute): CarbonImmutable
    {
        return $day->setTime(intdiv($minute, 60), $minute % 60);
    }

    /**
     * @return array{busy: list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>, blocks: Collection<int, ScheduleBlock>, staff: list<int>}
     */
    private function loadOccupation(CarbonImmutable $from, CarbonImmutable $to, ?int $excludeAppointmentId = null): array
    {
        return [
            'busy' => self::busyIntervals(Appointment::query()->confirmed()->overlapping($from, $to)
                ->when($excludeAppointmentId !== null, fn (Builder $query) => $query->whereKeyNot($excludeAppointmentId))
                ->get(['starts_at', 'ends_at', 'waits', 'hairdresser_id'])),
            'blocks' => ScheduleBlock::query()->overlapping($from, $to)->get(['starts_at', 'ends_at', 'capacity_reduction', 'hairdresser_id']),
            'staff' => self::activeStaff(),
        ];
    }

    /**
     * The active stretches of these appointments (outside their waits),
     * each with the appointment's hairdresser (null: any): the only time
     * they take a place. One appointment's stretches never overlap each
     * other, so counting the stretches covering a moment counts the
     * appointments being done at that moment.
     *
     * @param  Collection<int, Appointment>  $appointments
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>
     */
    private static function busyIntervals(Collection $appointments): array
    {
        return $appointments
            ->flatMap(fn (Appointment $appointment) => array_map(
                fn (array $interval) => [$interval[0], $interval[1], $appointment->hairdresser_id === null ? null : (int) $appointment->hairdresser_id],
                TimeProfile::fromAppointment($appointment)->activeIntervals($appointment->starts_at),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array{busy: list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>, blocks: Collection<int, ScheduleBlock>, staff: list<int>}  $context
     */
    private function hasCapacity(TimeProfile $profile, CarbonImmutable $start, array $context, ?int $hairdresserId): bool
    {
        return $this->capacityProblem($profile, $start, $context, $hairdresserId) === null;
    }

    /**
     * At the first moment without a free place (PRF-165):
     *
     * - Closed when, at any moment of the appointment, waits included,
     *   there is a full closure or no hairdresser is available: the
     *   available ones are the active ones ($context['staff']) without a
     *   block of their own then, minus the reductions of the unnamed
     *   "Reducir N" blocks saved before hairdressers existed.
     * - HairdresserAbsent when the appointment's own hairdresser is not
     *   available during one of its active stretches (a block of hers
     *   during its wait does not matter: she is not needed then).
     * - HairdresserBusy when its own hairdresser already has an
     *   appointment during one of its active stretches.
     * - Full when, during one of its active stretches, the appointments
     *   that any hairdresser has to do (those with none, an extra one of a
     *   hairdresser who already has one then, those of a hairdresser who
     *   is not available, and the unnamed reductions) outnumber the
     *   available hairdressers not busy with one of their own.
     *
     * Then (Full otherwise) the whole day must still be shareable out
     * without anybody taking over in the middle of an active stretch
     * (PRF-165, review H1): see sharesOut(). With no hairdresser named by
     * any appointment or block, that holds whenever the count does (the
     * stretches are intervals, so counting is enough), so the result is
     * exactly the old capacity count (capacity = active hairdressers).
     *
     * Occupancy can only rise, and availability only drop, where one of
     * the appointment's own active stretches starts, or where another
     * appointment's active stretch or a block starts inside it, so
     * checking those moments is exact whatever the slot interval.
     *
     * @param  array{busy: list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>, blocks: Collection<int, ScheduleBlock>, staff: list<int>}  $context
     */
    private function capacityProblem(TimeProfile $profile, CarbonImmutable $start, array $context, ?int $hairdresserId): ?UnavailabilityReason
    {
        $end = $start->addMinutes($profile->durationMinutes);
        $active = $profile->activeIntervals($start);
        $staff = array_flip($context['staff']);
        $covering = fn (CarbonImmutable $moment) => fn (array $interval) => $interval[0]->lte($moment) && $interval[1]->gt($moment);

        $moments = collect([$start])
            ->merge(array_column($active, 0))
            ->merge(array_column($context['busy'], 0))
            ->merge($context['blocks']->pluck('starts_at'))
            ->filter(fn (CarbonImmutable $moment) => $moment->gte($start) && $moment->lt($end));

        foreach ($moments as $moment) {
            $blocks = $context['blocks']->filter(fn (ScheduleBlock $block) => $block->starts_at->lte($moment) && $block->ends_at->gt($moment));

            if ($blocks->contains(fn (ScheduleBlock $block) => $block->isFullClosure())) {
                return UnavailabilityReason::Closed;
            }

            // A block of a hairdresser who is not active takes nobody away.
            $absent = [];
            $unnamedReduction = 0;

            foreach ($blocks as $block) {
                if ($block->hairdresser_id === null) {
                    $unnamedReduction += $block->capacity_reduction;
                } elseif (isset($staff[$block->hairdresser_id])) {
                    $absent[$block->hairdresser_id] = true;
                }
            }

            $available = array_diff_key($staff, $absent);

            if (count($available) - $unnamedReduction < 1) {
                return UnavailabilityReason::Closed;
            }

            // While the appointment is waiting it takes no place.
            if (array_filter($active, $covering($moment)) === []) {
                continue;
            }

            $ownBusy = [];
            $anyoneDemand = $unnamedReduction;

            foreach (array_filter($context['busy'], $covering($moment)) as [, , $busyHairdresser]) {
                if ($busyHairdresser !== null && isset($available[$busyHairdresser]) && ! isset($ownBusy[$busyHairdresser])) {
                    $ownBusy[$busyHairdresser] = true;
                } else {
                    $anyoneDemand++;
                }
            }

            if ($hairdresserId === null) {
                $anyoneDemand++;
            } elseif (! isset($available[$hairdresserId])) {
                return UnavailabilityReason::HairdresserAbsent;
            } elseif (isset($ownBusy[$hairdresserId])) {
                return UnavailabilityReason::HairdresserBusy;
            } else {
                $ownBusy[$hairdresserId] = true;
            }

            if ($anyoneDemand > count($available) - count($ownBusy)) {
                return UnavailabilityReason::Full;
            }
        }

        if (! $this->sharesOut($active, $context, $hairdresserId)) {
            return UnavailabilityReason::Full;
        }

        return null;
    }

    /**
     * Whether the day can still be shared out among the active
     * hairdressers with the new appointment's active stretches ($active)
     * added, so that each active stretch of every appointment is done by
     * one hairdresser from start to end (PRF-165, review H1): those with a
     * hairdresser by her, those with none by anybody free during all of it
     * (a different one after a wait is fine: each stretch stands alone).
     * A hairdresser's own blocks keep her out; each unit of an old unnamed
     * "Reducir N" takes somebody for the whole block.
     *
     * Only the groups of overlapping "anybody" stretches the new
     * appointment touches are solved (StaffAssignment), each on its own. A
     * group that could not be shared out even before the new appointment
     * (after a "Guardar igualmente") does not block it: the per-moment
     * count already passed, as it did before hairdressers existed, and the
     * agenda keeps showing that group as "Sobre capacidad".
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $active
     * @param  array{busy: list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>, blocks: Collection<int, ScheduleBlock>, staff: list<int>}  $context
     */
    private function sharesOut(array $active, array $context, ?int $hairdresserId): bool
    {
        $base = $this->shareBase($context);
        $machines = count($context['staff']);
        $newIntervals = array_map(fn (array $interval) => [$interval[0]->getTimestamp(), $interval[1]->getTimestamp()], $active);

        // Nobody named anywhere: the stretches are plain intervals, so the
        // per-moment count already checked is enough.
        if ($hairdresserId === null && $base['busy'] === []) {
            return true;
        }

        $touched = array_keys(array_filter($base['groups'], fn (array $group) => self::overlapsAny($newIntervals, $group['span'])));

        if ($hairdresserId === null) {
            $jobs = array_merge(...array_map(fn (int $group) => $base['groups'][$group]['jobs'], $touched), ...[$newIntervals]);

            if (StaffAssignment::solve($machines, $base['busy'], $jobs) !== null) {
                return true;
            }

            // Refused only if the day could be shared out before it.
            foreach ($touched as $group) {
                if (! $this->groupSharesOut($base, $group, $machines)) {
                    return true;
                }
            }

            return false;
        }

        $machine = array_flip($context['staff'])[$hairdresserId];
        $busyWithNew = $base['busy'];
        $busyWithNew[$machine] = array_merge($busyWithNew[$machine] ?? [], $newIntervals);

        foreach ($touched as $group) {
            if (StaffAssignment::solve($machines, $busyWithNew, $base['groups'][$group]['jobs']) === null
                && $this->groupSharesOut($base, $group, $machines)) {
                return false;
            }
        }

        return true;
    }

    /**
     * What sharesOut() needs from a context, worked out once per context
     * (the calendar checks every start time of up to a month against the
     * same one): each hairdresser's fixed busy times (her own stretches
     * and blocks), the stretches anybody can do (with no hairdresser, or
     * a second one of the same hairdresser at once, and each unit of an
     * old unnamed reduction), and those in groups that overlap.
     *
     * @param  array{busy: list<array{0: CarbonImmutable, 1: CarbonImmutable, 2: int|null}>, blocks: Collection<int, ScheduleBlock>, staff: list<int>}  $context
     * @return array{busy: array<int, list<array{0: int, 1: int}>>, groups: list<array{jobs: list<array{0: int, 1: int}>, span: array{0: int, 1: int}}>, sharesOut: array<int, bool>}
     */
    private function shareBase(array $context): array
    {
        $key = spl_object_id($context['blocks']);

        if (isset($this->shareBases[$key]) && $this->shareBases[$key]['blocks'] === $context['blocks']) {
            return $this->shareBases[$key]['base'];
        }

        $machineOf = array_flip($context['staff']);
        $busy = [];
        $jobs = [];

        foreach ($context['blocks'] as $block) {
            if ($block->hairdresser_id !== null && isset($machineOf[$block->hairdresser_id])) {
                $busy[$machineOf[$block->hairdresser_id]][] = [$block->starts_at->getTimestamp(), $block->ends_at->getTimestamp()];
            } elseif ($block->hairdresser_id === null && $block->capacity_reduction !== null) {
                for ($unit = 0; $unit < $block->capacity_reduction; $unit++) {
                    $jobs[] = [$block->starts_at->getTimestamp(), $block->ends_at->getTimestamp()];
                }
            }
        }

        $stretches = $context['busy'];
        usort($stretches, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $ownStretches = [];

        foreach ($stretches as [$start, $end, $hairdresser]) {
            $interval = [$start->getTimestamp(), $end->getTimestamp()];
            $machine = $hairdresser === null ? null : ($machineOf[$hairdresser] ?? null);

            // A second appointment of the same hairdresser at the same time
            // (only "Guardar igualmente" saves one) needs somebody else.
            if ($machine !== null && ! self::overlapsAny($ownStretches[$machine] ?? [], $interval)) {
                $ownStretches[$machine][] = $interval;
            } else {
                $jobs[] = $interval;
            }
        }

        foreach ($ownStretches as $machine => $intervals) {
            $busy[$machine] = array_merge($busy[$machine] ?? [], $intervals);
        }

        $groups = array_map(function (array $group) use ($jobs) {
            $groupJobs = array_map(fn (int $job) => $jobs[$job], $group);

            return ['jobs' => $groupJobs, 'span' => [min(array_column($groupJobs, 0)), max(array_column($groupJobs, 1))]];
        }, StaffAssignment::groups($jobs));

        $base = ['busy' => $busy, 'groups' => $groups, 'sharesOut' => []];
        $this->shareBases = [$key => ['blocks' => $context['blocks'], 'base' => $base]];

        return $base;
    }

    /**
     * Whether a group of shareBase() could be shared out as it is,
     * remembered with the base.
     *
     * @param  array{busy: array<int, list<array{0: int, 1: int}>>, groups: list<array{jobs: list<array{0: int, 1: int}>, span: array{0: int, 1: int}}>, sharesOut: array<int, bool>}  $base
     */
    private function groupSharesOut(array $base, int $group, int $machines): bool
    {
        $key = array_key_first($this->shareBases);

        return $this->shareBases[$key]['base']['sharesOut'][$group]
            ??= StaffAssignment::solve($machines, $base['busy'], $base['groups'][$group]['jobs']) !== null;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     * @param  array{0: int, 1: int}  $interval
     */
    private static function overlapsAny(array $intervals, array $interval): bool
    {
        foreach ($intervals as [$start, $end]) {
            if ($start < $interval[1] && $end > $interval[0]) {
                return true;
            }
        }

        return false;
    }
}
