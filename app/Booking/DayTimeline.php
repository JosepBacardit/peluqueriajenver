<?php

namespace App\Booking;

use App\Models\OpeningHour;

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
     * (PRF-108).
     *
     * @return array{start: int, end: int}
     */
    public static function weekBounds(): array
    {
        $hours = OpeningHour::query()->get(['opens_at', 'closes_at']);

        if ($hours->isEmpty()) {
            return ['start' => self::FALLBACK_START, 'end' => self::FALLBACK_END];
        }

        $open = $hours->min(fn (OpeningHour $hour) => $hour->opensAtMinutes());
        $close = $hours->max(fn (OpeningHour $hour) => $hour->closesAtMinutes());

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
}
