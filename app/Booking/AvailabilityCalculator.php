<?php

namespace App\Booking;

use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
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
        $settings = BookingSetting::current();
        $day = $day->startOfDay();

        if (! $this->isWithinBookingWindow($day, $now, $settings)) {
            return [];
        }

        $context = $this->loadOccupation($day, $day->addDay());
        $earliest = $now->addMinutes($settings->min_notice_minutes);
        $times = [];

        foreach ($this->rangesFor($day) as $range) {
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
     */
    public function isAvailable(int $durationMinutes, CarbonImmutable $start, CarbonImmutable $now, bool $applyPublicRules): bool
    {
        $settings = BookingSetting::current();
        $day = $start->startOfDay();
        $startMinute = $start->hour * 60 + $start->minute;
        $end = $start->addMinutes($durationMinutes);

        if ($start->second !== 0 || $start->lt($now)) {
            return false;
        }

        $range = $this->rangesFor($day)->first(
            fn (OpeningHour $range) => $startMinute >= $range->opensAtMinutes()
                && $startMinute + $durationMinutes <= $range->closesAtMinutes()
        );

        if ($range === null) {
            return false;
        }

        if ($applyPublicRules) {
            $onGrid = ($startMinute - $range->opensAtMinutes()) % $settings->slot_interval_minutes === 0;

            if (! $onGrid
                || $start->lt($now->addMinutes($settings->min_notice_minutes))
                || ! $this->isWithinBookingWindow($day, $now, $settings)) {
                return false;
            }
        }

        return $this->hasCapacity($start, $end, $this->loadOccupation($start, $end), $settings->capacity);
    }

    /**
     * Days between $from and $to (inclusive) with at least one public
     * start time.
     *
     * @return list<string> dates as Y-m-d
     */
    public function daysWithAvailability(int $durationMinutes, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        $days = [];

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            if ($this->availableStartTimes($durationMinutes, $day, $now) !== []) {
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
    private function loadOccupation(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'appointments' => Appointment::query()->confirmed()->overlapping($from, $to)->get(['starts_at', 'ends_at']),
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
        $moments = collect([$start])
            ->merge($context['appointments']->pluck('starts_at'))
            ->merge($context['blocks']->pluck('starts_at'))
            ->filter(fn (CarbonImmutable $moment) => $moment->gte($start) && $moment->lt($end));

        foreach ($moments as $moment) {
            $covers = fn ($item) => $item->starts_at->lte($moment) && $item->ends_at->gt($moment);

            $occupied = $context['appointments']->filter($covers)->count();
            $reduction = $context['blocks']->filter($covers)
                ->sum(fn (ScheduleBlock $block) => $block->capacity_reduction ?? $capacity);

            if ($capacity - $reduction - $occupied < 1) {
                return false;
            }
        }

        return true;
    }
}
