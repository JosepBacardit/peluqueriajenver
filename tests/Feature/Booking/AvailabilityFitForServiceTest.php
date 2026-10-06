<?php

use App\Booking\AvailabilityCalculator;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\OpeningHour;
use App\Models\ScheduleBlock;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * fittingStartMinutes(): for the agenda's service filter, which of the
 * candidate start times (minutes of the day) a service of a given length
 * fits into, from the context the agenda has already loaded. Rules 1 and
 * 2 only, never rule 3, and no queries.
 *
 * Reference day: Tuesday 2030-01-08, open 09:00-19:00 by the default
 * schedule; capacity 1 unless a test says otherwise.
 */
beforeEach(function () {
    BookingSetting::current()->update(['capacity' => 1, 'slot_interval_minutes' => 30, 'min_notice_minutes' => 600, 'max_advance_days' => 1]);
    $this->calculator = app(AvailabilityCalculator::class);
    $this->service = Service::factory()->create();
});

function fitMinute(string $time): int
{
    [$hour, $minute] = explode(':', $time);

    return (int) $hour * 60 + (int) $minute;
}

function fitBook(string $from, string $to, string $date = '2030-01-08', AppointmentStatus $status = AppointmentStatus::Confirmed): void
{
    Appointment::factory()->withServices(test()->service)->create([
        'starts_at' => CarbonImmutable::parse("{$date} {$from}"),
        'ends_at' => CarbonImmutable::parse("{$date} {$to}"),
        'status' => $status,
    ]);
}

function fitBlock(string $from, string $to, ?int $reduction, string $date = '2030-01-08'): void
{
    ScheduleBlock::create(['starts_at' => CarbonImmutable::parse("{$date} {$from}"), 'ends_at' => CarbonImmutable::parse("{$date} {$to}"), 'capacity_reduction' => $reduction]);
}

/**
 * The context exactly as AgendaController loads it for DayTimeline::build():
 * the day's opening ranges, its appointments of any status and the blocks
 * overlapping it, plus the capacity.
 *
 * @return array{day: CarbonImmutable, ranges: Collection, appointments: Collection, blocks: Collection, capacity: int}
 */
function agendaContext(string $date): array
{
    $day = CarbonImmutable::parse($date)->startOfDay();

    return [
        'day' => $day,
        'ranges' => OpeningHour::query()->get()->where('weekday', $day->isoWeekday())->sortBy('opens_at')->values(),
        'appointments' => Appointment::query()->where('starts_at', '>=', $day)->where('starts_at', '<', $day->addDay())->orderBy('starts_at')->get(),
        'blocks' => ScheduleBlock::query()->overlapping($day, $day->addDay())->orderBy('starts_at')->get(),
        'capacity' => BookingSetting::current()->capacity,
    ];
}

/**
 * @param  list<string>  $candidates  H:i
 * @return list<string> the candidates the service fits into, as H:i
 */
function fittingTimes(int $durationMinutes, array $candidates, string $date = '2030-01-08'): array
{
    $context = agendaContext($date);

    $minutes = app(AvailabilityCalculator::class)->fittingStartMinutes(
        $durationMinutes,
        array_map(fitMinute(...), $candidates),
        $context['day'],
        $context['ranges'],
        $context['appointments'],
        $context['blocks'],
        $context['capacity'],
    );

    return array_map(fn (int $minute) => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60), $minutes);
}

test('an appointment leaves no room for a service that would overlap it, but one ending as the candidate starts does', function () {
    fitBook('10:00', '11:00');

    expect(fittingTimes(60, ['09:00', '09:30', '10:00', '10:30', '11:00']))->toBe(['09:00', '11:00']);
});

test('a second place lets a service overlap one appointment but not two', function () {
    BookingSetting::current()->update(['capacity' => 2]);
    fitBook('10:00', '11:00');

    expect(fittingTimes(60, ['09:30', '10:00', '10:30']))->toBe(['09:30', '10:00', '10:30']);

    fitBook('10:30', '11:30');

    // 10:00 and 10:30 overlap both at 10:30; 09:30 and 11:00 only ever one.
    expect(fittingTimes(60, ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30']))->toBe(['09:00', '09:30', '11:00', '11:30']);
});

test('a capacity reduction starting halfway through the service counts from that moment', function () {
    BookingSetting::current()->update(['capacity' => 2]);
    fitBook('10:00', '12:00');
    fitBlock('11:00', '12:00', 1);

    // At 11:00 only one place is left, and the 10:00 appointment holds it.
    expect(fittingTimes(60, ['10:00', '10:30', '11:00', '12:00']))->toBe(['10:00', '12:00']);
});

test('a full closure leaves no room while it lasts', function () {
    BookingSetting::current()->update(['capacity' => 2]);
    fitBlock('14:00', '15:00', null);

    expect(fittingTimes(60, ['13:00', '13:30', '14:00', '14:30', '15:00']))->toBe(['13:00', '15:00']);
});

test('a service that would run over the lunch break or past closing does not fit', function () {
    OpeningHour::query()->where('weekday', 2)->delete();
    OpeningHour::create(['weekday' => 2, 'opens_at' => '09:00:00', 'closes_at' => '13:00:00']);
    OpeningHour::create(['weekday' => 2, 'opens_at' => '15:00:00', 'closes_at' => '19:00:00']);

    expect(fittingTimes(60, ['08:30', '12:00', '12:30', '13:00', '14:30', '15:00', '18:00', '18:30']))
        ->toBe(['12:00', '15:00', '18:00']);
});

test('a closed day has no room at all', function () {
    expect(fittingTimes(30, ['10:00', '12:00'], '2030-01-14'))->toBe([]);
});

test('cancelled appointments do not take a place', function () {
    fitBook('10:00', '11:00', status: AppointmentStatus::Cancelled);

    expect(fittingTimes(60, ['10:00', '10:30']))->toBe(['10:00', '10:30']);
});

test('rule 3 never applies: off-grid times and times inside the minimum notice or past the booking window fit', function () {
    // min_notice_minutes = 600 and max_advance_days = 1 (beforeEach), but
    // the method knows nothing about "now" or the slot interval.
    expect(fittingTimes(30, ['09:05', '09:10', '18:25'], '2030-02-12'))->toBe(['09:05', '09:10', '18:25']);
});

test('times stay on the local clock on the days the clocks change', function (string $date) {
    OpeningHour::create(['weekday' => 7, 'opens_at' => '09:00:00', 'closes_at' => '11:00:00']);
    fitBook('10:00', '10:30', $date);

    expect(fittingTimes(60, ['08:30', '09:00', '09:30', '10:00', '10:30'], $date))->toBe(['09:00']);
    expect(fittingTimes(30, ['09:30', '10:00', '10:30'], $date))->toBe(['09:30', '10:30']);
})->with([
    'summer time starts' => '2030-03-31',
    'summer time ends' => '2030-10-27',
]);

test('it runs no query', function () {
    BookingSetting::current()->update(['capacity' => 2]);
    fitBook('10:00', '11:00');
    fitBlock('15:00', '16:00', 1);
    $context = agendaContext('2030-01-08');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->calculator->fittingStartMinutes(90, range(fitMinute('08:00'), fitMinute('19:30'), 30), $context['day'], $context['ranges'], $context['appointments'], $context['blocks'], $context['capacity']);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([]);
});

/*
 * Property-like check: on many random days (seeded, so a failure is
 * reproducible) the method agrees with isAvailable(..., applyPublicRules:
 * false) for every candidate. "Now" is the evening before, so the only
 * rule isAvailable() adds for the panel (not in the past) never applies.
 */
test('it always agrees with isAvailable for the panel', function (int $seed) {
    mt_srand($seed);
    $date = '2030-01-08';
    $calculator = $this->calculator;
    $now = CarbonImmutable::parse('2030-01-07 20:00');

    BookingSetting::current()->update(['capacity' => mt_rand(1, 3)]);

    if (mt_rand(0, 1) === 1) {
        OpeningHour::query()->where('weekday', 2)->delete();
        OpeningHour::create(['weekday' => 2, 'opens_at' => '09:30:00', 'closes_at' => '13:15:00']);
        OpeningHour::create(['weekday' => 2, 'opens_at' => '15:00:00', 'closes_at' => '19:00:00']);
    }

    for ($i = mt_rand(0, 6); $i > 0; $i--) {
        $start = mt_rand(8 * 12, 19 * 12) * 5;
        $length = [15, 30, 45, 60, 90, 120][mt_rand(0, 5)];
        $status = mt_rand(0, 4) === 0 ? AppointmentStatus::Cancelled : AppointmentStatus::Confirmed;
        fitBook(sprintf('%02d:%02d', intdiv($start, 60), $start % 60), sprintf('%02d:%02d', intdiv($start + $length, 60), ($start + $length) % 60), $date, $status);
    }

    for ($i = mt_rand(0, 2); $i > 0; $i--) {
        $start = mt_rand(9 * 4, 18 * 4) * 15;
        $length = [30, 60, 120][mt_rand(0, 2)];
        fitBlock(sprintf('%02d:%02d', intdiv($start, 60), $start % 60), sprintf('%02d:%02d', intdiv($start + $length, 60), ($start + $length) % 60), mt_rand(0, 1) === 1 ? null : 1, $date);
    }

    $context = agendaContext($date);
    $candidates = range(fitMinute('08:00'), fitMinute('19:30'), 15);

    foreach ([30, 45, 60, 150] as $duration) {
        $fitting = $calculator->fittingStartMinutes($duration, $candidates, $context['day'], $context['ranges'], $context['appointments'], $context['blocks'], $context['capacity']);
        $expected = array_values(array_filter($candidates, fn (int $minute) => $calculator->isAvailable(
            $duration, $context['day']->setTime(intdiv($minute, 60), $minute % 60), $now, applyPublicRules: false,
        )));

        expect($fitting)->toBe($expected, "seed {$seed}, duration {$duration}");
    }
})->with(range(1, 25));
