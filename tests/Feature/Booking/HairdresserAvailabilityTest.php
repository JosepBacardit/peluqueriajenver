<?php

use App\Actions\CreateAppointment;
use App\Actions\RescheduleAppointment;
use App\Booking\AvailabilityCalculator;
use App\Booking\SlotUnavailableException;
use App\Booking\TimeProfile;
use App\Booking\UnavailabilityReason;
use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Hairdresser;
use App\Models\ScheduleBlock;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Choosing a hairdresser (PRF-161 to PRF-165): the capacity is the number
 * of active hairdressers; an appointment with a hairdresser needs her, one
 * with none needs anyone, and every appointment counts against the total.
 *
 * Reference day: Tuesday 2030-01-08, open 09:00-19:00 by the default
 * schedule; Jennifer and Isabel, created by the migration; "now" is the
 * evening before.
 */
beforeEach(function () {
    BookingSetting::current()->update(['slot_interval_minutes' => 15, 'min_notice_minutes' => 0, 'max_advance_days' => 60]);
    $this->calculator = app(AvailabilityCalculator::class);
    $this->now = CarbonImmutable::parse('2030-01-07 20:00');
    $this->jennifer = Hairdresser::query()->where('name', 'Jennifer')->sole();
    $this->isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
});

function hdAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2030-01-08 {$time}");
}

function hdBook(string $from, string $to, ?Hairdresser $hairdresser = null, array $waits = []): Appointment
{
    return Appointment::factory()->create([
        'starts_at' => hdAt($from),
        'ends_at' => hdAt($to),
        'waits' => $waits === [] ? null : $waits,
        'hairdresser_id' => $hairdresser?->id,
        'hairdresser_requested' => $hairdresser !== null,
    ]);
}

function hdBlock(string $from, string $to, ?Hairdresser $hairdresser = null, ?int $reduction = 1): ScheduleBlock
{
    return ScheduleBlock::create([
        'starts_at' => hdAt($from),
        'ends_at' => hdAt($to),
        'capacity_reduction' => $hairdresser !== null ? 1 : $reduction,
        'hairdresser_id' => $hairdresser?->id,
    ]);
}

function hdReason(TimeProfile|int $length, string $time, ?Hairdresser $hairdresser = null): ?UnavailabilityReason
{
    return test()->calculator->unavailabilityReason($length, hdAt($time), test()->now, false, hairdresserId: $hairdresser?->id);
}

test('the capacity is the number of active hairdressers', function () {
    hdBook('10:00', '11:00');
    expect(hdReason(60, '10:00'))->toBeNull();

    hdBook('10:00', '11:00');
    expect(hdReason(60, '10:00'))->toBe(UnavailabilityReason::Full);

    Hairdresser::factory()->create(['name' => 'Laura']);
    expect(hdReason(60, '10:00'))->toBeNull();

    Hairdresser::query()->where('name', 'Laura')->update(['is_active' => false]);
    expect(hdReason(60, '10:00'))->toBe(UnavailabilityReason::Full);
});

test('with no active hairdresser nothing can be booked and online booking is unavailable', function () {
    Service::factory()->create();
    expect(BookingSetting::onlineBookingAvailable())->toBeTrue();

    Hairdresser::query()->update(['is_active' => false]);

    expect(hdReason(60, '10:00'))->toBe(UnavailabilityReason::Closed)
        ->and(BookingSetting::onlineBookingAvailable())->toBeFalse();
});

test('a hairdresser who already has an appointment cannot take another at the same time, but the other one can', function () {
    hdBook('10:00', '11:00', $this->jennifer);

    expect(hdReason(30, '10:30', $this->jennifer))->toBe(UnavailabilityReason::HairdresserBusy)
        ->and(hdReason(30, '10:30', $this->isabel))->toBeNull()
        ->and(hdReason(30, '10:30'))->toBeNull()
        ->and(hdReason(30, '11:00', $this->jennifer))->toBeNull();
});

test('an appointment for any hairdresser takes one of them, so the other cannot be booked twice', function () {
    hdBook('10:00', '11:00', $this->jennifer);
    hdBook('10:00', '11:00');

    // Isabel is the only one who can do the unassigned appointment.
    expect(hdReason(30, '10:30', $this->isabel))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(30, '10:30'))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(30, '11:00', $this->isabel))->toBeNull();
});

test('two appointments for any hairdresser leave nobody for a chosen one', function () {
    hdBook('10:00', '11:00');
    hdBook('10:00', '11:00');

    expect(hdReason(30, '10:30', $this->jennifer))->toBe(UnavailabilityReason::Full);
});

test('a block of one hairdresser only takes her away', function () {
    hdBlock('10:00', '12:00', $this->isabel);

    expect(hdReason(60, '10:00', $this->isabel))->toBe(UnavailabilityReason::HairdresserAbsent)
        ->and(hdReason(60, '10:00', $this->jennifer))->toBeNull()
        ->and(hdReason(60, '10:00'))->toBeNull()
        ->and(hdReason(60, '12:00', $this->isabel))->toBeNull();

    hdBook('10:00', '11:00');

    expect(hdReason(60, '10:00'))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(60, '10:00', $this->jennifer))->toBe(UnavailabilityReason::Full);
});

test('both hairdressers on leave close the salon', function () {
    hdBlock('10:00', '12:00', $this->isabel);
    hdBlock('10:00', '12:00', $this->jennifer);

    expect(hdReason(60, '10:30'))->toBe(UnavailabilityReason::Closed);
});

test('a block of the chosen hairdresser during the wait of her appointment does not prevent it', function () {
    $coloracion = new TimeProfile(120, [['start' => 30, 'minutes' => 45]]);
    hdBlock('10:30', '11:15', $this->jennifer);

    expect(hdReason($coloracion, '10:00', $this->jennifer))->toBeNull()
        ->and(hdReason($coloracion, '10:15', $this->jennifer))->toBe(UnavailabilityReason::HairdresserAbsent);
});

test('the chosen hairdresser does both stretches of her appointment', function () {
    $coloracion = new TimeProfile(120, [['start' => 30, 'minutes' => 45]]);
    // Jennifer is busy when the colour comes back at 11:15.
    hdBook('11:00', '11:30', $this->jennifer);

    expect(hdReason($coloracion, '10:00', $this->jennifer))->toBe(UnavailabilityReason::HairdresserBusy)
        ->and(hdReason($coloracion, '10:00'))->toBeNull();
});

test('an appointment for any hairdresser needs one of them free for the whole of each stretch', function () {
    hdBook('10:00', '10:30', $this->jennifer);
    hdBook('10:30', '11:00', $this->isabel);

    // Someone is free at every moment, but nobody for the whole hour.
    expect(hdReason(60, '10:00'))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(30, '10:00'))->toBeNull();
});

test('the old unnamed reductions still count', function () {
    hdBlock('10:00', '12:00', reduction: 1);
    hdBook('10:00', '11:00');

    expect(hdReason(30, '10:30'))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(30, '10:30', $this->jennifer))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(30, '11:00', $this->jennifer))->toBeNull();
});

test('a block or an appointment of a deactivated hairdresser counts like one for anybody', function () {
    hdBlock('10:00', '12:00', $this->isabel);
    hdBook('10:00', '11:00', $this->isabel);
    $this->isabel->update(['is_active' => false]);

    // Only Jennifer is left, and she has to do Isabel's appointment.
    expect(hdReason(30, '10:30'))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(30, '11:00'))->toBeNull()
        ->and(hdReason(30, '11:00', $this->isabel))->toBe(UnavailabilityReason::HairdresserAbsent);
});

test('the public times depend on the hairdresser chosen', function () {
    hdBook('09:00', '19:00', $this->jennifer);

    expect($this->calculator->availableStartTimes(60, hdAt('00:00'), $this->now, $this->jennifer->id))->toBe([])
        ->and($this->calculator->availableStartTimes(60, hdAt('00:00'), $this->now, $this->isabel->id))->not->toBe([])
        ->and($this->calculator->daysWithAvailability(60, hdAt('00:00'), hdAt('00:00'), $this->now, $this->jennifer->id))->toBe([])
        ->and($this->calculator->daysWithAvailability(60, hdAt('00:00'), hdAt('00:00'), $this->now))->toBe(['2030-01-08']);
});

test('a booking with a hairdresser records her and that the customer chose her', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $customer = ['customer_name' => 'Ana', 'customer_phone' => '600123456', 'customer_email' => 'ana@example.com', 'notes' => null];
    $create = app(CreateAppointment::class);

    $chosen = $create->handle(collect([$service]), hdAt('10:00'), $customer, AppointmentSource::Web, applyPublicRules: false, now: $this->now, hairdresserId: $this->jennifer->id);
    $any = $create->handle(collect([$service]), hdAt('12:00'), $customer, AppointmentSource::Web, applyPublicRules: false, now: $this->now);

    expect($chosen->fresh()->hairdresser_id)->toBe($this->jennifer->id)
        ->and($chosen->fresh()->hairdresser_requested)->toBeTrue()
        ->and($any->fresh()->hairdresser_id)->toBeNull()
        ->and($any->fresh()->hairdresser_requested)->toBeFalse();

    expect(fn () => $create->handle(collect([$service]), hdAt('10:30'), [...$customer, 'customer_email' => 'b@example.com'], AppointmentSource::Web, applyPublicRules: false, now: $this->now, hairdresserId: $this->jennifer->id))
        ->toThrow(SlotUnavailableException::class);
});

test('moving an appointment to another hairdresser checks her availability', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $appointment = Appointment::factory()->withServices($service)->create(['starts_at' => hdAt('10:00'), 'hairdresser_id' => $this->jennifer->id, 'hairdresser_requested' => true]);
    hdBook('10:00', '11:00', $this->isabel);
    $customer = ['customer_name' => $appointment->customer_name, 'customer_phone' => $appointment->customer_phone, 'customer_email' => $appointment->customer_email, 'notes' => null];
    $move = app(RescheduleAppointment::class);

    try {
        $move->handle($appointment, collect([$service]), hdAt('10:00'), $customer, false, $this->now, hairdresserId: $this->isabel->id);
        $this->fail('Isabel is busy');
    } catch (SlotUnavailableException $exception) {
        expect($exception->reason)->toBe(UnavailabilityReason::HairdresserBusy);
    }

    $outcome = $move->handle($appointment->fresh(), collect([$service]), hdAt('10:00'), $customer, true, $this->now, hairdresserId: $this->isabel->id);

    expect($appointment->fresh()->hairdresser_id)->toBe($this->isabel->id)
        ->and($appointment->fresh()->hairdresser_requested)->toBeTrue()
        ->and($outcome->rescheduled)->toBeTrue();
});

test('the salon assigning an unassigned appointment does not count as the customer choosing her, nor as a change for her', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $appointment = Appointment::factory()->withServices($service)->create(['starts_at' => hdAt('10:00')]);
    $customer = ['customer_name' => $appointment->customer_name, 'customer_phone' => $appointment->customer_phone, 'customer_email' => $appointment->customer_email, 'notes' => null];

    $outcome = app(RescheduleAppointment::class)->handle($appointment, collect([$service]), hdAt('10:00'), $customer, false, $this->now, hairdresserId: $this->isabel->id);

    expect($appointment->fresh()->hairdresser_id)->toBe($this->isabel->id)
        ->and($appointment->fresh()->hairdresser_requested)->toBeFalse()
        ->and($outcome->rescheduled)->toBeFalse();
});

test('taking the hairdresser away leaves the appointment for anybody, and moving keeps her by default', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $appointment = Appointment::factory()->withServices($service)->create(['starts_at' => hdAt('10:00'), 'hairdresser_id' => $this->jennifer->id, 'hairdresser_requested' => true]);
    $customer = ['customer_name' => $appointment->customer_name, 'customer_phone' => $appointment->customer_phone, 'customer_email' => $appointment->customer_email, 'notes' => null];
    $move = app(RescheduleAppointment::class);

    $move->handle($appointment, collect([$service]), hdAt('11:00'), $customer, false, $this->now);
    expect($appointment->fresh()->hairdresser_id)->toBe($this->jennifer->id)
        ->and($appointment->fresh()->hairdresser_requested)->toBeTrue();

    $outcome = $move->handle($appointment->fresh(), collect([$service]), hdAt('11:00'), $customer, false, $this->now, hairdresserId: null);
    expect($appointment->fresh()->hairdresser_id)->toBeNull()
        ->and($appointment->fresh()->hairdresser_requested)->toBeFalse()
        ->and($outcome->rescheduled)->toBeTrue();
});

test('an appointment for anybody is never left needing a hand-over in the middle, by later bookings with a hairdresser', function () {
    // Review H1: anybody 10:00-11:00 first, then Isabel 09:30-10:30.
    hdBook('10:00', '11:00');
    hdBook('09:30', '10:30', $this->isabel);

    // Jennifer 10:30-11:30 would leave the first one with Jennifer until
    // 10:30 and Isabel after: refused.
    expect(hdReason(60, '10:30', $this->jennifer))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(60, '11:00', $this->jennifer))->toBeNull();
});

test('an appointment for anybody is never left needing a hand-over by another one for anybody', function () {
    // Review H1, with three hairdressers.
    setStaffCount(3);
    $third = Hairdresser::query()->where('name', 'Peluquera 3')->sole();
    hdBook('10:00', '11:00');
    hdBook('10:30', '11:00', $this->jennifer);
    hdBook('09:00', '10:15', $third);

    // Only Isabel can do 10:00-11:00 the whole way; a second one for
    // anybody at 10:00-10:45 would need her too.
    expect(hdReason(45, '10:00'))->toBe(UnavailabilityReason::Full)
        ->and(hdReason(45, '11:00'))->toBeNull();
});

test('assigning an appointment from the panel is refused when it would leave another without anybody for the whole stretch', function () {
    $service = Service::factory()->create(['duration_minutes' => 60]);
    hdBook('10:00', '11:00');
    $other = Appointment::factory()->withServices($service)->create(['starts_at' => hdAt('10:30')]);
    hdBook('09:30', '10:30', $this->isabel);
    $customer = ['customer_name' => $other->customer_name, 'customer_phone' => $other->customer_phone, 'customer_email' => $other->customer_email, 'notes' => null];

    // Giving the 10:30-11:30 one to Jennifer leaves 10:00-11:00 without
    // anybody for the whole hour.
    expect(fn () => app(RescheduleAppointment::class)->handle($other, collect([$service]), hdAt('10:30'), $customer, false, $this->now, hairdresserId: $this->jennifer->id))
        ->toThrow(SlotUnavailableException::class);
});

test('a day that already needed "save anyway" does not block other times of that group', function () {
    setStaffCount(1);
    hdBook('10:00', '11:00');
    hdBook('10:30', '11:30');

    expect(hdReason(30, '11:30'))->toBeNull()
        ->and(hdReason(30, '10:45'))->toBe(UnavailabilityReason::Full);
});

/*
 * Brute force, independent of the engine: on random days, every way of
 * giving each stretch of an appointment for anybody (and each unit of an
 * old unnamed reduction) to a hairdresser is tried; the new appointment
 * fits when one of them leaves nobody with two things at once and nobody
 * working while she is away, and no full closure covers it. Days that
 * could not be shared out even before are generated again.
 */
test('it agrees with trying every way to share the day out', function (int $seed) {
    mt_srand($seed);
    setStaffCount(mt_rand(2, 3));
    $staff = AvailabilityCalculator::activeStaff();
    $time = fn (int $minute) => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    $overlaps = fn (array $a, array $b) => $a[0] < $b[1] && $b[0] < $a[1];

    // $fixed: hairdresser => intervals she cannot take anything else in.
    // $anybody: intervals somebody has to take.
    $feasible = function (array $fixed, array $anybody) use ($staff, $overlaps): bool {
        foreach ($fixed as $intervals) {
            foreach ($intervals as $i => $a) {
                foreach (array_slice($intervals, $i + 1) as $b) {
                    if ($overlaps($a, $b)) {
                        return false;
                    }
                }
            }
        }

        $count = count($anybody);
        for ($code = 0; $code < count($staff) ** $count; $code++) {
            $load = $fixed;
            $ok = true;
            $rest = $code;
            foreach ($anybody as $interval) {
                $hairdresser = $staff[$rest % count($staff)];
                $rest = intdiv($rest, count($staff));
                foreach ($load[$hairdresser] ?? [] as $taken) {
                    if ($overlaps($taken, $interval)) {
                        $ok = false;
                        break 2;
                    }
                }
                $load[$hairdresser][] = $interval;
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    };

    do {
        Appointment::query()->delete();
        ScheduleBlock::query()->delete();
        $fixed = [];
        $anybody = [];
        $closures = [];

        for ($i = mt_rand(1, 6); $i > 0; $i--) {
            $start = mt_rand(9 * 12, 12 * 12) * 5;
            $interval = [$start, $start + [15, 30, 45, 60][mt_rand(0, 3)]];
            $hairdresser = mt_rand(0, 1) === 1 ? null : $staff[mt_rand(0, count($staff) - 1)];
            Appointment::factory()->create(['starts_at' => hdAt($time($interval[0])), 'ends_at' => hdAt($time($interval[1])), 'hairdresser_id' => $hairdresser]);
            if ($hairdresser === null) {
                $anybody[] = $interval;
            } else {
                $fixed[$hairdresser][] = $interval;
            }
        }

        for ($i = mt_rand(0, 2); $i > 0; $i--) {
            $start = mt_rand(9 * 4, 12 * 4) * 15;
            $interval = [$start, $start + [30, 60][mt_rand(0, 1)]];
            $kind = mt_rand(0, 5);
            $hairdresser = $kind <= 3 ? $staff[mt_rand(0, count($staff) - 1)] : null;
            ScheduleBlock::create(['starts_at' => hdAt($time($interval[0])), 'ends_at' => hdAt($time($interval[1])), 'capacity_reduction' => $kind === 5 ? null : 1, 'hairdresser_id' => $hairdresser]);
            match (true) {
                $kind === 5 => $closures[] = $interval,
                $hairdresser === null => $anybody[] = $interval,
                default => $fixed[$hairdresser][] = $interval,
            };
        }
    } while (! $feasible($fixed, $anybody));

    for ($check = 0; $check < 25; $check++) {
        $start = mt_rand(9 * 12, 12 * 12) * 5;
        $interval = [$start, $start + [15, 30, 60][mt_rand(0, 2)]];
        $chosen = mt_rand(0, 1) === 1 ? null : $staff[mt_rand(0, count($staff) - 1)];
        $closed = array_filter($closures, fn (array $closure) => $overlaps($closure, $interval)) !== [];
        $withChosen = $fixed;
        $withChosen[$chosen ?? 0][] = $interval;
        $expected = ! $closed && ($chosen === null
            ? $feasible($fixed, [...$anybody, $interval])
            : $feasible($withChosen, $anybody));

        expect(test()->calculator->isAvailable($interval[1] - $interval[0], hdAt($time($interval[0])), test()->now, false, hairdresserId: $chosen))
            ->toBe($expected, "seed {$seed}: {$time($interval[0])}-{$time($interval[1])}, hairdresser ".($chosen ?? 'any'));
    }
})->with(range(1, 40));
