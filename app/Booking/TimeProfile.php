<?php

namespace App\Booking;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * How long a service (or a whole appointment) lasts and when, inside that
 * time, the hairdresser is free: the waits (e.g. a dye's processing time)
 * during which the customer stays in the salon but needs nobody, so the
 * hairdresser can take someone else.
 *
 * Each wait is ['start' => minutes from the start, 'minutes' => length].
 * Waits are always between two active stretches (never at the very start
 * or end), in order and without overlapping. The parts outside the waits
 * are the active intervals: the only ones that take a place of capacity.
 *
 * A profile with no waits is the same as the plain duration everything
 * used before waits existed.
 */
final readonly class TimeProfile
{
    /**
     * The most waits one service can have, set from the panel.
     */
    public const MAX_WAITS_PER_SERVICE = 2;

    /**
     * @var list<array{start: int, minutes: int}>
     */
    public array $waits;

    /**
     * @param  iterable<array{start: int|string, minutes: int|string}>|null  $waits
     *
     * @throws InvalidArgumentException when a wait is out of place
     */
    public function __construct(public int $durationMinutes, ?iterable $waits = [])
    {
        $normalized = [];
        $previousEnd = 0;

        foreach ($waits ?? [] as $wait) {
            $start = (int) $wait['start'];
            $minutes = (int) $wait['minutes'];

            if ($minutes < 1 || $start <= $previousEnd || $start + $minutes >= $durationMinutes) {
                throw new InvalidArgumentException('Each wait must sit between two active stretches, in order.');
            }

            $normalized[] = ['start' => $start, 'minutes' => $minutes];
            $previousEnd = $start + $minutes;
        }

        $this->waits = $normalized;
    }

    /**
     * Int callers mean a plain duration with no waits.
     */
    public static function of(self|int $length): self
    {
        return $length instanceof self ? $length : new self($length);
    }

    /**
     * Services (or their frozen copies on an appointment, as models or
     * arrays) done one after another, in the given order: their durations
     * add up and each one's waits move along by the time before it.
     *
     * @param  iterable<object|array{duration_minutes: int, waits?: iterable|null}>  $services
     */
    public static function fromServices(iterable $services): self
    {
        $offset = 0;
        $waits = [];

        foreach ($services as $service) {
            $duration = (int) data_get($service, 'duration_minutes');

            foreach (self::storedWaits($duration, data_get($service, 'waits'), 'service '.data_get($service, 'service_id', data_get($service, 'id'))) as $wait) {
                $waits[] = ['start' => $offset + $wait['start'], 'minutes' => $wait['minutes']];
            }

            $offset += $duration;
        }

        return new self($offset, $waits);
    }

    /**
     * From the steps the salon types in the service form, in the order
     * they happen: work, wait, work, wait, work (every other one a wait).
     *
     * @param  list<int>  $steps
     */
    public static function fromSteps(array $steps): self
    {
        $offset = 0;
        $waits = [];

        foreach (array_values($steps) as $index => $minutes) {
            if ($index % 2 === 1) {
                $waits[] = ['start' => $offset, 'minutes' => $minutes];
            }

            $offset += $minutes;
        }

        return new self($offset, $waits);
    }

    /**
     * The reverse of fromSteps(): the minutes of each work and wait, in
     * order, e.g. [30, 45, 45].
     *
     * @return list<int>
     */
    public function steps(): array
    {
        $steps = [];

        foreach ($this->activeOffsets() as $index => [$from, $to]) {
            if ($index > 0) {
                $steps[] = $this->waits[$index - 1]['minutes'];
            }

            $steps[] = $to - $from;
        }

        return $steps;
    }

    /**
     * The profile an appointment was booked with: its length and the waits
     * frozen on it (none for an appointment booked before waits existed).
     */
    public static function fromAppointment(Appointment $appointment): self
    {
        $duration = $appointment->durationMinutes();

        return new self($duration, self::storedWaits($duration, $appointment->waits, 'appointment '.$appointment->id));
    }

    /**
     * Stored waits, read tolerantly (review L5): what is written is always
     * checked (ServiceRequest, fromSteps(), the constructor), but a row
     * that no longer fits (e.g. its duration changed by hand) must not
     * break the booking page or the agenda. It is read as having no waits
     * — taking more room, never less, so it can never cause an
     * overbooking — and logged so it can be fixed.
     *
     * @return list<array{start: int, minutes: int}>
     */
    private static function storedWaits(int $durationMinutes, mixed $waits, string $what): array
    {
        if ($waits === null || $waits === []) {
            return [];
        }

        try {
            return (new self($durationMinutes, $waits))->waits;
        } catch (Throwable) {
            Log::warning("Stored waits do not fit {$what} ({$durationMinutes} min); read as no waits.", ['waits' => $waits]);

            return [];
        }
    }

    /**
     * The stretches that need a hairdresser, as [from, to) minutes from
     * the start.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function activeOffsets(): array
    {
        $intervals = [];
        $cursor = 0;

        foreach ($this->waits as $wait) {
            $intervals[] = [$cursor, $wait['start']];
            $cursor = $wait['start'] + $wait['minutes'];
        }

        $intervals[] = [$cursor, $this->durationMinutes];

        return $intervals;
    }

    /**
     * activeOffsets() as moments for an appointment starting at $start.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function activeIntervals(CarbonImmutable $start): array
    {
        return array_map(
            fn (array $interval) => [$start->addMinutes($interval[0]), $start->addMinutes($interval[1])],
            $this->activeOffsets(),
        );
    }

    /**
     * The waits as moments for an appointment starting at $start.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function waitIntervals(CarbonImmutable $start): array
    {
        return array_map(
            fn (array $wait) => [$start->addMinutes($wait['start']), $start->addMinutes($wait['start'] + $wait['minutes'])],
            $this->waits,
        );
    }

    /**
     * Total minutes of waiting inside the duration.
     */
    public function waitMinutes(): int
    {
        return array_sum(array_column($this->waits, 'minutes'));
    }

    /**
     * Waits as stored in a `waits` JSON column: null when there are none.
     *
     * @return list<array{start: int, minutes: int}>|null
     */
    public function waitsForStorage(): ?array
    {
        return $this->waits === [] ? null : $this->waits;
    }

    public function equals(self $other): bool
    {
        return $this->durationMinutes === $other->durationMinutes && $this->waits === $other->waits;
    }
}
