<?php

namespace App\Booking;

use App\Models\OpeningHour;
use Illuminate\Support\Collection;

/**
 * The salon's opening hours, read from the panel (PRF-135, PRF-136): the
 * single source for every public place that used to have them written by
 * hand (the footer, the home page, the FAQ about online booking, the
 * contact page's meta description and the JSON-LD schema). One query per
 * request: plain php-fpm runs one process per request, so a static
 * property already behaves like a per-request cache without needing a
 * Laravel Cache store (see OpeningHoursSummaryTest, which asserts a single
 * query across several calls).
 */
final class OpeningHoursSummary
{
    private const ENGLISH_WEEKDAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /** @var array<int, list<array{opens: string, closes: string}>>|null */
    private static ?array $byWeekday = null;

    /**
     * One line for the whole week, grouping consecutive days that share
     * the exact same ranges, e.g. "Martes a viernes: 9:00–14:00 y
     * 16:00–20:00 · Sábado: 9:00–14:00 · Domingo y lunes: cerrado".
     */
    public static function text(): string
    {
        return self::groups()
            ->map(fn (array $group) => self::dayLabel($group['weekdays']).': '.self::rangesLabel($group['ranges']))
            ->join(' · ');
    }

    /**
     * One schema.org OpeningHoursSpecification entry per day-group and
     * range. Closed-day groups are omitted entirely, which is what
     * schema.org itself recommends instead of a 00:00-00:00 placeholder.
     *
     * @return list<array{'@type': string, dayOfWeek: list<string>, opens: string, closes: string}>
     */
    public static function schemaSpecifications(): array
    {
        $specs = [];

        foreach (self::groups() as $group) {
            if ($group['ranges'] === []) {
                continue;
            }

            $days = array_map(fn (int $weekday) => self::ENGLISH_WEEKDAYS[$weekday], $group['weekdays']);

            foreach ($group['ranges'] as $range) {
                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $days,
                    'opens' => $range['opens'],
                    'closes' => $range['closes'],
                ];
            }
        }

        return $specs;
    }

    /**
     * Clears the per-request cache. Only tests need this: a request that
     * saves the schedule and then reads these pages in the very same PHP
     * process (as a Pest test does, unlike a real browser round trip)
     * would otherwise still see the schedule read before the save.
     */
    public static function forgetCachedSchedule(): void
    {
        self::$byWeekday = null;
    }

    /**
     * Consecutive weekdays (Monday=1 to Sunday=7) sharing the exact same
     * ranges, grouped into runs. A run ending at Sunday and one starting
     * at Monday with the same ranges are circular neighbours, so they are
     * merged into one run and moved to the end of the list (instead of
     * leaving Monday stuck first) — e.g. "Domingo y lunes: cerrado".
     *
     * @return Collection<int, array{weekdays: list<int>, ranges: list<array{opens: string, closes: string}>}>
     */
    private static function groups(): Collection
    {
        $groups = [];

        foreach (self::rangesByWeekday() as $weekday => $ranges) {
            $lastIndex = $groups === [] ? null : array_key_last($groups);

            if ($lastIndex !== null && self::sameRanges($groups[$lastIndex]['ranges'], $ranges)) {
                $groups[$lastIndex]['weekdays'][] = $weekday;
            } else {
                $groups[] = ['weekdays' => [$weekday], 'ranges' => $ranges];
            }
        }

        if (count($groups) > 1 && self::sameRanges($groups[0]['ranges'], $groups[array_key_last($groups)]['ranges'])) {
            $first = array_shift($groups);
            $wrapped = array_pop($groups);
            $groups[] = [
                'weekdays' => [...$wrapped['weekdays'], ...$first['weekdays']],
                'ranges' => $first['ranges'],
            ];
        }

        return collect($groups);
    }

    /**
     * @param  list<array{opens: string, closes: string}>  $a
     * @param  list<array{opens: string, closes: string}>  $b
     */
    private static function sameRanges(array $a, array $b): bool
    {
        return $a === $b;
    }

    /**
     * @param  list<int>  $weekdays  in the order they should read, already a contiguous run (circular or not)
     */
    private static function dayLabel(array $weekdays): string
    {
        $names = array_map(fn (int $weekday) => OpeningHour::WEEKDAY_NAMES[$weekday], $weekdays);

        if (count($names) === 1) {
            return $names[0];
        }

        if (count($names) === 2) {
            return $names[0].' y '.mb_strtolower($names[1]);
        }

        return $names[0].' a '.mb_strtolower($names[count($names) - 1]);
    }

    /**
     * @param  list<array{opens: string, closes: string}>  $ranges
     */
    private static function rangesLabel(array $ranges): string
    {
        if ($ranges === []) {
            return 'cerrado';
        }

        return collect($ranges)
            ->map(fn (array $range) => self::formatTime($range['opens']).'–'.self::formatTime($range['closes']))
            ->join(' y ');
    }

    /**
     * "09:00" -> "9:00": schema.org gets the zero-padded HH:MM as stored,
     * but a sentence for people reads better without the leading zero.
     */
    private static function formatTime(string $time): string
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours.':'.sprintf('%02d', $minutes);
    }

    /**
     * @return array<int, list<array{opens: string, closes: string}>> one entry per ISO weekday (1-7), in opening order
     */
    private static function rangesByWeekday(): array
    {
        if (self::$byWeekday !== null) {
            return self::$byWeekday;
        }

        $byWeekday = [];

        foreach (array_keys(OpeningHour::WEEKDAY_NAMES) as $weekday) {
            $byWeekday[$weekday] = [];
        }

        foreach (OpeningHour::query()->orderBy('weekday')->orderBy('opens_at')->get() as $hour) {
            $byWeekday[$hour->weekday][] = [
                'opens' => substr($hour->opens_at, 0, 5),
                'closes' => substr($hour->closes_at, 0, 5),
            ];
        }

        return self::$byWeekday = $byWeekday;
    }
}
