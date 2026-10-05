<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides which start times can be booked for a given duration.
 *
 * A start time is available when:
 *  1. the whole appointment fits inside one opening range of that day;
 *  2. at no moment of the appointment do the confirmed appointments that
 *     overlap it reach the effective capacity (the capacity setting minus
 *     the reductions of the blocks covering that moment; a full closure
 *     leaves it at 0);
 *  3. public bookings only: it is on the slot-interval grid counted from
 *     the start of the range, not earlier than now + minimum notice, and
 *     its day is not later than today + maximum advance.
 *
 * Settings and schedule are read on every call (never cached on the
 * instance), so a re-check inside the booking transaction always sees the
 * current values.
 */
class AvailabilityCalculator
{
    /**
     * Start times offered on the public booking page for one day.
     *
     * @return list<CarbonImmutable>
     */
    public function availableStartTimes(int $durationMinutes, CarbonImmutable $day, CarbonImmutable $now): array
    {
        $day = $day->startOfDay();

        return $this->startTimesFor(
            $durationMinutes, $day, $now,
            BookingSetting::current(),
            $this->rangesFor($day),
            $this->loadOccupation($day, $day->addDay()),
        );
    }

    /**
     * @param  Collection<int, OpeningHour>  $ranges  the day's opening ranges
     * @param  array{appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>}  $context  occupation covering at least the day
     * @return list<CarbonImmutable>
     */
    private function startTimesFor(int $durationMinutes, CarbonImmutable $day, CarbonImmutable $now, BookingSetting $settings, Collection $ranges, array $context): array
    {
        if (! $this->isWithinBookingWindow($day, $now, $settings)) {
            return [];
        }

        $earliest = $now->addMinutes($settings->min_notice_minutes);
        $times = [];

        foreach ($ranges as $range) {
            for ($minute = $range->opensAtMinutes(); $minute + $durationMinutes <= $range->closesAtMinutes(); $minute += $settings->slot_interval_minutes) {
                $start = $this->atMinute($day, $minute);

                if ($start->lt($earliest)) {
                    continue;
                }

                if ($this->hasCapacity($start, $start->addMinutes($durationMinutes), $context, $settings->capacity)) {
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
    public function isAvailable(int $durationMinutes, CarbonImmutable $start, CarbonImmutable $now, bool $applyPublicRules, ?int $excludeAppointmentId = null): bool
    {
        return $this->unavailabilityReason($durationMinutes, $start, $now, $applyPublicRules, $excludeAppointmentId) === null;
    }

    /**
     * Why one specific start time cannot be booked (same rules and
     * parameters as isAvailable()), or null when it can. The panel uses it
     * to tell the salon what is wrong with the time it chose.
     */
    public function unavailabilityReason(int $durationMinutes, CarbonImmutable $start, CarbonImmutable $now, bool $applyPublicRules, ?int $excludeAppointmentId = null): ?UnavailabilityReason
    {
        $settings = BookingSetting::current();
        $day = $start->startOfDay();
        $startMinute = $start->hour * 60 + $start->minute;
        $end = $start->addMinutes($durationMinutes);

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

        return $this->capacityProblem($start, $end, $this->loadOccupation($start, $end, $excludeAppointmentId), $settings->capacity);
    }

    /**
     * Which of the candidate start times a service of $durationMinutes
     * fits into on $day, by rules 1 and 2 only (an opening range holds the
     * whole service, and the effective capacity, after the blocks' reductions
     * and full closures, is never reached by the confirmed appointments):
     * the same rules and the same capacity check (capacityProblem()) as
     * isAvailable(..., applyPublicRules: false). Rule 3 (slot interval,
     * minimum notice, booking window) never applies, and neither does
     * isAvailable()'s "not in the past": the agenda decides what to do with
     * past times. A test checks it agrees with isAvailable() on many
     * random days.
     *
     * It runs no query: everything comes from the context the agenda
     * already loads for DayTimeline::build(), so the agenda's service
     * filter costs nothing per day, lane or half hour. Usage, in
     * AgendaController::dayData() (and per day in weekData(), with that
     * day's $dayRanges, $dayAppointments and $dayBlocks):
     *
     *     $fitting = $calculator->fittingStartMinutes(
     *         $service->duration_minutes,
     *         $candidateMinutes, // e.g. the 'start' of the timeline's tappable free segments
     *         $day, $dayRanges, $appointments, $blocks, $capacity,
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
     * @return list<int> the candidates the service fits into, in the given order
     */
    public function fittingStartMinutes(int $durationMinutes, array $candidateMinutes, CarbonImmutable $day, Collection $ranges, Collection $appointments, Collection $blocks, int $capacity): array
    {
        $day = $day->startOfDay();
        $context = [
            'appointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->isConfirmed())->values(),
            'blocks' => $blocks,
        ];

        return array_values(array_filter($candidateMinutes, function (int $minute) use ($durationMinutes, $day, $ranges, $context, $capacity): bool {
            if ($this->rangeContaining($ranges, $minute, $durationMinutes) === null) {
                return false;
            }

            $start = $this->atMinute($day, $minute);

            return $this->hasCapacity($start, $start->addMinutes($durationMinutes), $context, $capacity);
        }));
    }

    /**
     * Days between $from and $to (inclusive) with at least one public
     * start time.
     *
     * @return list<string> dates as Y-m-d
     */
    public function daysWithAvailability(int $durationMinutes, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        // Loaded once for the whole range (a handful of queries for a month
        // view instead of four per day).
        $settings = BookingSetting::current();
        $rangesByWeekday = OpeningHour::query()->orderBy('opens_at')->get()->groupBy('weekday');
        $context = $this->loadOccupation($from->startOfDay(), $to->startOfDay()->addDay());
        $days = [];

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $ranges = $rangesByWeekday->get($day->isoWeekday(), collect());

            if ($this->startTimesFor($durationMinutes, $day, $now, $settings, $ranges, $context) !== []) {
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
     * @return array{appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>}
     */
    private function loadOccupation(CarbonImmutable $from, CarbonImmutable $to, ?int $excludeAppointmentId = null): array
    {
        return [
            'appointments' => Appointment::query()->confirmed()->overlapping($from, $to)
                ->when($excludeAppointmentId !== null, fn (Builder $query) => $query->whereKeyNot($excludeAppointmentId))
                ->get(['starts_at', 'ends_at']),
            'blocks' => ScheduleBlock::query()->overlapping($from, $to)->get(['starts_at', 'ends_at', 'capacity_reduction']),
        ];
    }

    /**
     * Occupancy can only rise at the appointment's own start or where
     * another appointment or block starts inside it, so checking those
     * moments is exact whatever the slot interval.
     *
     * @param  array{appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>}  $context
     */
    private function hasCapacity(CarbonImmutable $start, CarbonImmutable $end, array $context, int $capacity): bool
    {
        return $this->capacityProblem($start, $end, $context, $capacity) === null;
    }

    /**
     * At the first moment without a free place: Closed when the blocks
     * alone leave no place (a one-off closure), Full when the confirmed
     * appointments take the places left.
     *
     * @param  array{appointments: Collection<int, Appointment>, blocks: Collection<int, ScheduleBlock>}  $context
     */
    private function capacityProblem(CarbonImmutable $start, CarbonImmutable $end, array $context, int $capacity): ?UnavailabilityReason
    {
        $moments = collect([$start])
            ->merge($context['appointments']->pluck('starts_at'))
            ->merge($context['blocks']->pluck('starts_at'))
            ->filter(fn (CarbonImmutable $moment) => $moment->gte($start) && $moment->lt($end));

        foreach ($moments as $moment) {
            $covers = fn ($item) => $item->starts_at->lte($moment) && $item->ends_at->gt($moment);

            $occupied = $context['appointments']->filter($covers)->count();
            $reduction = $context['blocks']->filter($covers)
                ->sum(fn (ScheduleBlock $block) => $block->capacity_reduction ?? $capacity);

            if ($capacity - $reduction < 1) {
                return UnavailabilityReason::Closed;
            }

            if ($capacity - $reduction - $occupied < 1) {
                return UnavailabilityReason::Full;
            }
        }

        return null;
    }
}
