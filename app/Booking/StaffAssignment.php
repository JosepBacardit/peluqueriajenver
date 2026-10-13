<?php

namespace App\Booking;

/**
 * Whether some stretches of work ("jobs") can be shared out among a few
 * hairdressers ("machines") so that each job is done by one hairdresser
 * from start to end (PRF-165: nobody takes over in the middle of an
 * active stretch; a different one after a wait is fine, because each
 * active stretch is its own job).
 *
 * Each machine may already have fixed busy times (the stretches of the
 * appointments assigned to her, and her own blocks): a job can only go to
 * a machine with nothing during the whole job. Jobs given to the same
 * machine cannot overlap either (ending exactly when another starts is
 * not an overlap).
 *
 * Exact search (backtracking) in start order. It stays small: a salon has
 * 2 or 3 hairdressers and a handful of overlapping stretches, and
 * machines in the same state (same jobs still running, same fixed busy
 * times from here on) are tried only once. If the search ever needed more
 * than $limit steps, it gives up and answers "no" — never "yes" without a
 * real assignment, so it can never let two appointments share a
 * hairdresser.
 *
 * Pure computation; times are plain integers (e.g. timestamps).
 */
final class StaffAssignment
{
    public const DEFAULT_LIMIT = 20000;

    /**
     * @param  int  $machines  how many hairdressers (machines 0 to $machines - 1)
     * @param  array<int, list<array{0: int, 1: int}>>  $busy  machine => fixed busy intervals [start, end)
     * @param  list<array{0: int, 1: int}>  $jobs  intervals [start, end) to share out
     * @param  array<int, list<int>>  $preference  job index => machines to try first, in order
     * @return array<int, int>|null job index => machine, or null when there is no way (or the search ran out of steps)
     */
    public static function solve(int $machines, array $busy, array $jobs, array $preference = [], int $limit = self::DEFAULT_LIMIT): ?array
    {
        if ($jobs === []) {
            return [];
        }

        if ($machines < 1) {
            return null;
        }

        $order = array_keys($jobs);
        usort($order, fn (int $a, int $b) => $jobs[$a][0] <=> $jobs[$b][0] ?: $jobs[$a][1] <=> $jobs[$b][1]);

        $runningUntil = array_fill(0, $machines, PHP_INT_MIN);
        $assignment = [];
        $steps = 0;

        $search = function (int $position) use (&$search, &$runningUntil, &$assignment, &$steps, $order, $jobs, $busy, $machines, $preference, $limit): bool {
            if ($position === count($order)) {
                return true;
            }

            if (++$steps > $limit) {
                return false;
            }

            $job = $order[$position];
            [$start, $end] = $jobs[$job];
            $tried = [];

            foreach (self::candidateOrder($machines, $preference[$job] ?? [], $busy, $start) as $machine) {
                if ($runningUntil[$machine] > $start || self::overlapsAny($busy[$machine] ?? [], $start, $end)) {
                    continue;
                }

                // Two machines free now and with the same fixed busy times
                // from here on are interchangeable: try only one.
                $state = self::stateFrom($busy[$machine] ?? [], $start);
                if (! in_array($machine, $preference[$job] ?? [], true) && isset($tried[$state])) {
                    continue;
                }
                $tried[$state] = true;

                $previous = $runningUntil[$machine];
                $runningUntil[$machine] = $end;
                $assignment[$job] = $machine;

                if ($search($position + 1)) {
                    return true;
                }

                $runningUntil[$machine] = $previous;
                unset($assignment[$job]);
            }

            return false;
        };

        return $search(0) ? $assignment : null;
    }

    /**
     * Preferred machines first, then the one whose next fixed busy time
     * comes soonest (a tight fit leaves the others free for longer).
     *
     * @param  list<int>  $preferred
     * @param  array<int, list<array{0: int, 1: int}>>  $busy
     * @return list<int>
     */
    private static function candidateOrder(int $machines, array $preferred, array $busy, int $from): array
    {
        $rest = array_values(array_diff(range(0, $machines - 1), $preferred));
        $nextBusy = fn (int $machine) => min(array_merge([PHP_INT_MAX], array_column(array_filter($busy[$machine] ?? [], fn (array $interval) => $interval[1] > $from), 0)));
        usort($rest, fn (int $a, int $b) => $nextBusy($a) <=> $nextBusy($b) ?: $a <=> $b);

        return array_merge(array_values(array_filter($preferred, fn (int $machine) => $machine >= 0 && $machine < $machines)), $rest);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private static function overlapsAny(array $intervals, int $start, int $end): bool
    {
        foreach ($intervals as [$busyStart, $busyEnd]) {
            if ($busyStart < $end && $busyEnd > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private static function stateFrom(array $intervals, int $from): string
    {
        $ahead = array_values(array_filter($intervals, fn (array $interval) => $interval[1] > $from));
        sort($ahead);

        return json_encode($ahead);
    }

    /**
     * Groups jobs that overlap, directly or through others: different
     * groups can never compete for a hairdresser, so each can be solved on
     * its own.
     *
     * @param  list<array{0: int, 1: int}>  $jobs
     * @return list<list<int>> job indexes per group
     */
    public static function groups(array $jobs): array
    {
        $order = array_keys($jobs);
        usort($order, fn (int $a, int $b) => $jobs[$a][0] <=> $jobs[$b][0]);
        $groups = [];
        $groupEnd = PHP_INT_MIN;

        foreach ($order as $job) {
            if ($groups === [] || $jobs[$job][0] >= $groupEnd) {
                $groups[] = [$job];
                $groupEnd = $jobs[$job][1];
            } else {
                $groups[array_key_last($groups)][] = $job;
                $groupEnd = max($groupEnd, $jobs[$job][1]);
            }
        }

        return $groups;
    }
}
