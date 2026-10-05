<?php

use App\Booking\AvailabilityCalculator;
use App\Booking\UnavailabilityReason;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Reference day: Tuesday 2030-01-08, open 09:00-19:00 by the default
 * schedule. "Now" is the evening before unless a test says otherwise.
 */
beforeEach(function () {
    BookingSetting::current()->update(['capacity' => 2, 'slot_interval_minutes' => 15, 'min_notice_minutes' => 0, 'max_advance_days' => 60]);
    $this->calculator = app(AvailabilityCalculator::class);
    $this->now = CarbonImmutable::parse('2030-01-07 20:00');
});

function at(string $time, string $date = '2030-01-08'): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}");
}

function bookAppointment(string $from, string $to, string $date = '2030-01-08', AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
{
    return Appointment::factory()->create([
        'starts_at' => at($from, $date),
        'ends_at' => at($to, $date),
        'status' => $status,
    ]);
}

/**
 * @param  list<CarbonImmutable>  $times
 * @return list<string>
 */
function asTimes(array $times): array
{
    return array_map(fn (CarbonImmutable $time) => $time->format('H:i'), $times);
}

test('the six worked examples of the specification', function (array $appointments, array $blocks, int $duration, string $time, bool $expected) {
    foreach ($appointments as [$from, $to]) {
        bookAppointment($from, $to);
    }

    foreach ($blocks as [$from, $to, $reduction]) {
        ScheduleBlock::create(['starts_at' => at($from), 'ends_at' => at($to), 'capacity_reduction' => $reduction]);
    }

    expect($this->calculator->isAvailable($duration, at($time), $this->now, applyPublicRules: true))->toBe($expected);
    expect(in_array($time, asTimes($this->calculator->availableStartTimes($duration, at('00:00'), $this->now))))->toBe($expected);
})->with([
    'ends exactly at closing time' => [[], [], 60, '18:00', true],
    'ends after closing time' => [[], [], 60, '18:15', false],
    'two overlapping appointments fill the capacity' => [[['10:00', '11:00'], ['10:30', '11:30']], [], 30, '10:30', false],
    'one place frees up at 11:00' => [[['10:00', '11:00'], ['10:30', '11:30']], [], 30, '11:00', true],
    'a block reduces the capacity to one, already taken' => [[['10:00', '12:00']], [['11:00', '13:00', 1]], 30, '11:30', false],
    'crosses a full closure' => [[], [['14:00', '15:00', null]], 90, '13:00', false],
]);

test('start times follow the slot interval from the start of each range', function () {
    BookingSetting::current()->update(['slot_interval_minutes' => 30]);

    $times = asTimes($this->calculator->availableStartTimes(60, at('00:00'), $this->now));

    expect($times[0])->toBe('09:00');
    expect($times[1])->toBe('09:30');
    expect(end($times))->toBe('18:00');
    expect($this->calculator->isAvailable(60, at('09:15'), $this->now, applyPublicRules: true))->toBeFalse();
});

test('a split schedule offers no times across the lunch break', function () {
    OpeningHour::query()->where('weekday', 2)->delete();
    OpeningHour::create(['weekday' => 2, 'opens_at' => '09:00:00', 'closes_at' => '13:00:00']);
    OpeningHour::create(['weekday' => 2, 'opens_at' => '15:00:00', 'closes_at' => '19:00:00']);

    $times = asTimes($this->calculator->availableStartTimes(60, at('00:00'), $this->now));

    expect($times)->toContain('12:00')->toContain('15:00');
    expect($times)->not->toContain('12:15')->not->toContain('14:00')->not->toContain('14:45');
});

test('a closed day has no times', function () {
    // 2030-01-07 is a Monday, closed by default.
    expect($this->calculator->availableStartTimes(30, at('00:00', '2030-01-07'), CarbonImmutable::parse('2030-01-06 10:00')))->toBe([]);
});

test('cancelled appointments do not take capacity', function () {
    bookAppointment('10:00', '11:00', status: AppointmentStatus::Cancelled);
    bookAppointment('10:00', '11:00', status: AppointmentStatus::Cancelled);

    expect($this->calculator->isAvailable(60, at('10:00'), $this->now, applyPublicRules: true))->toBeTrue();
});

test('an appointment ending when another starts does not overlap it', function () {
    bookAppointment('09:00', '10:00');
    bookAppointment('09:00', '10:00');

    expect($this->calculator->isAvailable(60, at('10:00'), $this->now, applyPublicRules: true))->toBeTrue();
    expect($this->calculator->isAvailable(60, at('09:45'), $this->now, applyPublicRules: true))->toBeFalse();
});

test('the capacity setting is respected', function () {
    BookingSetting::current()->update(['capacity' => 1]);
    bookAppointment('10:00', '11:00');

    expect($this->calculator->isAvailable(30, at('10:30'), $this->now, applyPublicRules: true))->toBeFalse();
});

test('times before now plus the minimum notice are not offered publicly', function () {
    BookingSetting::current()->update(['min_notice_minutes' => 120]);
    $now = at('10:05');

    $times = asTimes($this->calculator->availableStartTimes(30, at('00:00'), $now));

    expect($times[0])->toBe('12:15');
    expect($this->calculator->isAvailable(30, at('12:00'), $now, applyPublicRules: true))->toBeFalse();
});

test('days beyond the maximum advance are not offered publicly', function () {
    BookingSetting::current()->update(['max_advance_days' => 1]);
    $now = CarbonImmutable::parse('2030-01-07 20:00');

    expect($this->calculator->availableStartTimes(30, at('00:00', '2030-01-08'), $now))->not->toBe([]);
    expect($this->calculator->availableStartTimes(30, at('00:00', '2030-01-09'), $now))->toBe([]);
    expect($this->calculator->lastBookableDay($now)->toDateString())->toBe('2030-01-08');
});

test('admin rules ignore the interval and the notice but not the past, the schedule or the capacity', function () {
    BookingSetting::current()->update(['min_notice_minutes' => 600]);
    $now = at('10:00');

    expect($this->calculator->isAvailable(30, at('10:05'), $now, applyPublicRules: false))->toBeTrue();
    expect($this->calculator->isAvailable(30, at('09:55'), $now, applyPublicRules: false))->toBeFalse();
    expect($this->calculator->isAvailable(30, at('18:45'), $now, applyPublicRules: false))->toBeFalse();

    bookAppointment('11:00', '12:00');
    bookAppointment('11:00', '12:00');
    expect($this->calculator->isAvailable(30, at('11:30'), $now, applyPublicRules: false))->toBeFalse();
});

test('an appointment being moved does not take capacity from its own new time', function () {
    BookingSetting::current()->update(['capacity' => 1]);
    $moved = bookAppointment('10:00', '11:00');

    // Moving it 15 minutes earlier overlaps only its own current place.
    expect($this->calculator->isAvailable(60, at('09:45'), $this->now, applyPublicRules: false))->toBeFalse();
    expect($this->calculator->isAvailable(60, at('09:45'), $this->now, applyPublicRules: false, excludeAppointmentId: $moved->id))->toBeTrue();

    // Every other appointment still counts.
    bookAppointment('09:00', '10:00');
    expect($this->calculator->isAvailable(60, at('09:45'), $this->now, applyPublicRules: false, excludeAppointmentId: $moved->id))->toBeFalse();
});

test('it says why a time cannot be booked', function () {
    BookingSetting::current()->update(['capacity' => 1, 'min_notice_minutes' => 120]);
    bookAppointment('10:00', '11:00');
    ScheduleBlock::create(['starts_at' => at('15:00'), 'ends_at' => at('16:00'), 'capacity_reduction' => null]);
    $now = at('08:00');

    expect($this->calculator->unavailabilityReason(60, at('07:00'), $now, applyPublicRules: false))->toBe(UnavailabilityReason::InThePast);
    expect($this->calculator->unavailabilityReason(60, at('18:30'), $now, applyPublicRules: false))->toBe(UnavailabilityReason::OutsideOpeningHours);
    expect($this->calculator->unavailabilityReason(60, at('10:30'), $now, applyPublicRules: false))->toBe(UnavailabilityReason::Full);
    expect($this->calculator->unavailabilityReason(60, at('14:30'), $now, applyPublicRules: false))->toBe(UnavailabilityReason::Closed);
    expect($this->calculator->unavailabilityReason(60, at('09:00'), $now, applyPublicRules: true))->toBe(UnavailabilityReason::OutsidePublicRules);
    expect($this->calculator->unavailabilityReason(60, at('12:00'), $now, applyPublicRules: false))->toBeNull();
    expect($this->calculator->isAvailable(60, at('12:00'), $now, applyPublicRules: false))->toBeTrue();
});

test('a full closure covering the whole day removes every time', function () {
    ScheduleBlock::create(['starts_at' => at('00:00'), 'ends_at' => at('00:00', '2030-01-09'), 'capacity_reduction' => null]);

    expect($this->calculator->availableStartTimes(30, at('00:00'), $this->now))->toBe([]);
});

test('days with availability in a range are listed', function () {
    // Monday 2030-01-07 closed, Tuesday-Saturday open, Sunday closed.
    $days = $this->calculator->daysWithAvailability(30, at('00:00', '2030-01-07'), at('00:00', '2030-01-13'), CarbonImmutable::parse('2030-01-06 10:00'));

    expect($days)->toBe(['2030-01-08', '2030-01-09', '2030-01-10', '2030-01-11', '2030-01-12']);
});

test('offered times stay on the local clock on the day summer time starts', function () {
    // Sunday 2030-03-31: clocks go from 02:00 to 03:00 in Madrid.
    OpeningHour::create(['weekday' => 7, 'opens_at' => '09:00:00', 'closes_at' => '11:00:00']);

    $times = asTimes($this->calculator->availableStartTimes(60, at('00:00', '2030-03-31'), CarbonImmutable::parse('2030-03-30 10:00')));

    expect($times[0])->toBe('09:00');
    expect(end($times))->toBe('10:00');
});
