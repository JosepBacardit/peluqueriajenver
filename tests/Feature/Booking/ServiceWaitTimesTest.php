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
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Waits inside a service (e.g. a dye's processing time): the customer
 * stays, but the hairdresser is free for someone else, so only the active
 * stretches take a place of capacity.
 *
 * Reference day: Tuesday 2030-01-08, open 09:00-19:00 by the default
 * schedule; capacity 2; "now" is the evening before. "Coloración" lasts
 * 120 minutes: 30 active, 45 waiting (from minute 30), 45 active.
 */
beforeEach(function () {
    BookingSetting::current()->update(['capacity' => 2, 'slot_interval_minutes' => 15, 'min_notice_minutes' => 0, 'max_advance_days' => 60]);
    $this->calculator = app(AvailabilityCalculator::class);
    $this->now = CarbonImmutable::parse('2030-01-07 20:00');
    $this->coloracion = Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]], 'sort_order' => 1]);
    $this->corte = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 45, 'sort_order' => 2]);
});

function waitAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2030-01-08 {$time}");
}

/**
 * A confirmed appointment from $from to $to, with these waits (minutes
 * from its own start).
 */
function waitBook(string $from, string $to, array $waits = []): Appointment
{
    return Appointment::factory()->create([
        'starts_at' => waitAt($from),
        'ends_at' => waitAt($to),
        'waits' => $waits === [] ? null : $waits,
    ]);
}

function coloracionProfile(): TimeProfile
{
    return new TimeProfile(120, [['start' => 30, 'minutes' => 45]]);
}

function waitCustomer(): array
{
    return ['customer_name' => 'Ana', 'customer_phone' => '600 111 222', 'customer_email' => null, 'notes' => null];
}

test('a profile splits into the active stretches around its waits', function () {
    expect(coloracionProfile()->activeOffsets())->toBe([[0, 30], [75, 120]]);
    expect((new TimeProfile(60))->activeOffsets())->toBe([[0, 60]]);
    expect((new TimeProfile(150, [['start' => 20, 'minutes' => 30], ['start' => 80, 'minutes' => 20]]))->activeOffsets())
        ->toBe([[0, 20], [50, 80], [100, 150]]);
});

test('services done one after another chain their waits', function () {
    $profile = TimeProfile::fromServices([$this->corte, $this->coloracion]);

    expect($profile->durationMinutes)->toBe(165);
    expect($profile->waits)->toBe([['start' => 75, 'minutes' => 45]]);
    expect($profile->activeOffsets())->toBe([[0, 75], [120, 165]]);
});

test('a wait must sit between two active stretches', function (array $waits) {
    new TimeProfile(120, $waits);
})->with([
    'at the very start' => [[['start' => 0, 'minutes' => 30]]],
    'at the very end' => [[['start' => 90, 'minutes' => 30]]],
    'past the end' => [[['start' => 100, 'minutes' => 30]]],
    'overlapping the previous one' => [[['start' => 20, 'minutes' => 30], ['start' => 40, 'minutes' => 10]]],
    'touching the previous one' => [[['start' => 20, 'minutes' => 30], ['start' => 50, 'minutes' => 10]]],
    'empty' => [[['start' => 20, 'minutes' => 0]]],
])->throws(InvalidArgumentException::class);

test('the worked examples of the specification', function (array $appointments, array $blocks, TimeProfile|int $candidate, string $time, bool $expected) {
    foreach ($appointments as [$from, $to, $waits]) {
        waitBook($from, $to, $waits);
    }

    foreach ($blocks as [$from, $to, $reduction]) {
        ScheduleBlock::create(['starts_at' => waitAt($from), 'ends_at' => waitAt($to), 'capacity_reduction' => $reduction]);
    }

    expect($this->calculator->isAvailable($candidate, waitAt($time), $this->now, applyPublicRules: true))->toBe($expected);
    expect(in_array(waitAt($time), $this->calculator->availableStartTimes($candidate, waitAt('00:00'), $this->now)))->toBe($expected);
})->with([
    'another service fits into a wait' => [[['10:00', '12:00', [['start' => 30, 'minutes' => 45]]], ['10:00', '10:45', []]], [], 60, '10:30', true],
    'the same appointment without the wait leaves no room' => [[['10:00', '12:00', []], ['10:00', '10:45', []]], [], 60, '10:30', false],
    'the service would still be running when the wait ends' => [[['10:00', '12:00', [['start' => 30, 'minutes' => 45]]], ['10:00', '11:30', []]], [], 60, '10:30', false],
    'the new service waits while two others are done' => [[['10:00', '10:45', []], ['10:00', '10:45', []]], [], coloracionProfile(), '09:30', true],
    'the new service would need a place that is taken after its wait' => [[['10:00', '11:00', []], ['10:00', '11:00', []]], [], coloracionProfile(), '09:30', false],
    'a full closure during the wait still closes the salon' => [[], [['10:45', '11:00', null]], coloracionProfile(), '10:00', false],
    'a capacity reduction during the wait does not matter' => [[['10:00', '12:00', []]], [['10:30', '11:15', 1]], coloracionProfile(), '10:00', true],
]);

test('a full closure during the wait is reported as closed, a place taken after it as full', function () {
    ScheduleBlock::create(['starts_at' => waitAt('10:45'), 'ends_at' => waitAt('11:00'), 'capacity_reduction' => null]);
    waitBook('14:00', '15:00');
    waitBook('14:00', '15:00');

    expect($this->calculator->unavailabilityReason(coloracionProfile(), waitAt('10:00'), $this->now, applyPublicRules: false))->toBe(UnavailabilityReason::Closed);
    expect($this->calculator->unavailabilityReason(coloracionProfile(), waitAt('13:30'), $this->now, applyPublicRules: false))->toBe(UnavailabilityReason::Full);
    expect($this->calculator->unavailabilityReason(coloracionProfile(), waitAt('12:00'), $this->now, applyPublicRules: false))->toBeNull();
});

test('the waits still count towards the opening hours: the whole appointment must fit in one range', function () {
    expect($this->calculator->isAvailable(coloracionProfile(), waitAt('17:00'), $this->now, applyPublicRules: false))->toBeTrue();
    expect($this->calculator->isAvailable(coloracionProfile(), waitAt('17:15'), $this->now, applyPublicRules: false))->toBeFalse();
});

test('a day is offered on the calendar when only a wait leaves room', function () {
    BookingSetting::current()->update(['capacity' => 1]);
    // The only place is taken all day except during this appointment's wait.
    waitBook('09:00', '19:00', [['start' => 90, 'minutes' => 60]]);

    expect($this->calculator->daysWithAvailability(60, waitAt('00:00'), waitAt('00:00'), $this->now))->toBe(['2030-01-08']);
    expect(array_map(fn ($time) => $time->format('H:i'), $this->calculator->availableStartTimes(60, waitAt('00:00'), $this->now)))->toBe(['10:30']);
});

test('the public booking page offers a time that only fits thanks to a wait', function () {
    $this->travelTo($this->now);
    waitBook('10:00', '12:00', [['start' => 30, 'minutes' => 45]]);
    waitBook('10:00', '10:45');
    $peinado = Service::factory()->create(['name' => 'Peinado', 'duration_minutes' => 60]);

    $this->get(route('reservas', ['servicio' => $peinado->id, 'fecha' => '2030-01-08']))
        ->assertOk()
        ->assertSee('value="10:30"', false)
        ->assertDontSee('value="10:15"', false);
});

test('a booking freezes the waits of each service and of the whole appointment', function () {
    $this->corte->update(['sort_order' => 0]);
    // Chosen in any order, done in the salon's: Corte, then Coloración.
    $appointment = app(CreateAppointment::class)->handle(collect([$this->coloracion, $this->corte]), waitAt('10:00'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);

    expect($appointment->fresh()->ends_at->format('H:i'))->toBe('12:45');
    expect($appointment->fresh()->waits)->toBe([['start' => 75, 'minutes' => 45]]);
    expect($appointment->fresh()->items->pluck('waits')->all())->toBe([null, [['start' => 30, 'minutes' => 45]]]);

    // Changing the service later never changes the appointment.
    $this->coloracion->update(['waits' => [['start' => 40, 'minutes' => 20]]]);

    expect($appointment->fresh()->waits)->toBe([['start' => 75, 'minutes' => 45]]);
    expect($appointment->fresh()->items->last()->waits)->toBe([['start' => 30, 'minutes' => 45]]);
});

test('a booking takes the room a wait leaves, and only that room', function () {
    $create = app(CreateAppointment::class);
    $create->handle(collect([$this->coloracion]), waitAt('10:00'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);
    waitBook('10:00', '10:45');
    $peinado = Service::factory()->create(['duration_minutes' => 60]);
    $largo = Service::factory()->create(['duration_minutes' => 75]);

    $create->handle(collect([$peinado]), waitAt('10:30'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);

    expect(fn () => $create->handle(collect([$largo]), waitAt('10:45'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now))
        ->toThrow(SlotUnavailableException::class);
});

test('an appointment booked before its service had waits keeps none', function () {
    $appointment = app(CreateAppointment::class)->handle(collect([$this->corte]), waitAt('10:00'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);
    $this->corte->update(['waits' => [['start' => 15, 'minutes' => 15]]]);

    expect($appointment->fresh()->waits)->toBeNull();
    expect(TimeProfile::fromAppointment($appointment->fresh())->activeOffsets())->toBe([[0, 45]]);
});

test('moving an appointment keeps the waits it was booked with', function () {
    $appointment = app(CreateAppointment::class)->handle(collect([$this->coloracion]), waitAt('10:00'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);
    $this->coloracion->update(['waits' => null]);

    app(RescheduleAppointment::class)->handle($appointment, collect([$this->coloracion]), waitAt('12:00'), waitCustomer(), ignoreHoursAndCapacity: false, now: $this->now);

    expect($appointment->fresh()->waits)->toBe([['start' => 30, 'minutes' => 45]]);
    expect($appointment->fresh()->ends_at->format('H:i'))->toBe('14:00');
});

test('changing the services rebuilds the waits from the kept and the new ones', function () {
    $appointment = app(CreateAppointment::class)->handle(collect([$this->coloracion]), waitAt('10:00'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);
    $this->coloracion->update(['waits' => null]);

    app(RescheduleAppointment::class)->handle($appointment, collect([$this->coloracion, $this->corte]), waitAt('10:00'), waitCustomer(), ignoreHoursAndCapacity: false, now: $this->now);

    expect($appointment->fresh()->waits)->toBe([['start' => 30, 'minutes' => 45]]);
    expect($appointment->fresh()->items->pluck('waits')->all())->toBe([[['start' => 30, 'minutes' => 45]], null]);
});

test('a change that keeps the start and the length but not the waits is checked again', function () {
    $sinEspera = Service::factory()->create(['duration_minutes' => 120, 'sort_order' => 1]);
    $appointment = app(CreateAppointment::class)->handle(collect([$this->coloracion]), waitAt('10:00'), waitCustomer(), AppointmentSource::Admin, applyPublicRules: false, now: $this->now);
    waitBook('10:00', '10:45');
    waitBook('10:30', '11:15');

    expect(fn () => app(RescheduleAppointment::class)->handle($appointment, collect([$sinEspera]), waitAt('10:00'), waitCustomer(), ignoreHoursAndCapacity: false, now: $this->now))
        ->toThrow(SlotUnavailableException::class);

    expect($appointment->fresh()->waits)->toBe([['start' => 30, 'minutes' => 45]]);
});

test('the agenda fit check counts only the active stretches', function () {
    waitBook('10:00', '12:00', [['start' => 30, 'minutes' => 45]]);
    waitBook('10:00', '10:45');
    $day = waitAt('00:00');

    $fitting = $this->calculator->fittingStartMinutes(
        60,
        [600, 630, 645, 660],
        $day,
        OpeningHour::query()->where('weekday', 2)->get(),
        Appointment::query()->get(),
        collect(),
        2,
    );

    expect($fitting)->toBe([630, 645, 660]);
});

/*
 * Brute force, minute by minute, as an independent check of the moments
 * the calculator inspects: a start time fits when no minute of the whole
 * appointment is fully closed and, in every minute of its active
 * stretches, the active stretches of the other appointments plus the
 * blocks' reductions leave a place. Seeded, so a failure is reproducible.
 */
test('it agrees minute by minute with a brute-force count', function (int $seed) {
    mt_srand($seed);
    $capacity = mt_rand(1, 3);
    BookingSetting::current()->update(['capacity' => $capacity]);
    $busyMinutes = []; // minute of the day => appointments active then
    $reduction = [];

    for ($i = mt_rand(1, 8); $i > 0; $i--) {
        $start = mt_rand(9 * 12, 17 * 12) * 5;
        $length = [30, 45, 60, 90, 120][mt_rand(0, 4)];
        $waits = $length >= 60 && mt_rand(0, 1) === 1 ? [['start' => 15, 'minutes' => $length - 30]] : [];
        $appointment = waitBook(sprintf('%02d:%02d', intdiv($start, 60), $start % 60), sprintf('%02d:%02d', intdiv($start + $length, 60), ($start + $length) % 60), $waits);

        foreach (TimeProfile::fromAppointment($appointment)->activeOffsets() as [$from, $to]) {
            for ($minute = $start + $from; $minute < $start + $to; $minute++) {
                $busyMinutes[$minute] = ($busyMinutes[$minute] ?? 0) + 1;
            }
        }
    }

    for ($i = mt_rand(0, 2); $i > 0; $i--) {
        $start = mt_rand(9 * 4, 18 * 4) * 15;
        $length = [15, 30, 60][mt_rand(0, 2)];
        $cut = mt_rand(0, 1) === 1 ? null : 1;
        ScheduleBlock::create(['starts_at' => waitAt('00:00')->addMinutes($start), 'ends_at' => waitAt('00:00')->addMinutes($start + $length), 'capacity_reduction' => $cut]);

        for ($minute = $start; $minute < $start + $length; $minute++) {
            $reduction[$minute] = ($reduction[$minute] ?? 0) + ($cut ?? $capacity);
        }
    }

    $profiles = [new TimeProfile(45), coloracionProfile(), new TimeProfile(150, [['start' => 20, 'minutes' => 30], ['start' => 80, 'minutes' => 20]])];

    foreach ($profiles as $profile) {
        $active = [];
        foreach ($profile->activeOffsets() as [$from, $to]) {
            $active = array_merge($active, range($from, $to - 1));
        }
        $active = array_flip($active);

        for ($start = 9 * 60; $start + $profile->durationMinutes <= 19 * 60; $start += 15) {
            $fits = true;

            for ($offset = 0; $offset < $profile->durationMinutes && $fits; $offset++) {
                $left = $capacity - ($reduction[$start + $offset] ?? 0);
                $fits = $left >= 1 && (! isset($active[$offset]) || $left - ($busyMinutes[$start + $offset] ?? 0) >= 1);
            }

            expect($this->calculator->isAvailable($profile, waitAt('00:00')->addMinutes($start), $this->now, applyPublicRules: false))
                ->toBe($fits, "seed {$seed}, start {$start}, profile ".json_encode($profile));
        }
    }
})->with(range(1, 15));
