<?php

use App\Actions\CancelAppointment;
use App\Actions\RescheduleAppointment;
use App\Booking\AppointmentNotMovableException;
use App\Booking\SlotUnavailableException;
use App\Booking\StartTimeInPastException;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * Reference day: Tuesday 2030-01-08, open 09:00-19:00 by the default
 * schedule. "Now" is the evening before.
 */
beforeEach(function () {
    BookingSetting::current()->update(['capacity' => 1, 'min_notice_minutes' => 0]);
    $this->now = CarbonImmutable::parse('2030-01-07 20:00');
    $this->service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);
    $this->appointment = Appointment::factory()->create([
        'service_id' => $this->service->id,
        'starts_at' => '2030-01-08 10:00',
        'ends_at' => '2030-01-08 11:00',
        'customer_name' => 'Rosa Vidal',
        'customer_phone' => '600 123 456',
        'customer_email' => 'rosa@example.test',
        'notes' => 'Pelo rizado',
    ]);
});

/**
 * @return array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}
 */
function unchangedCustomer(Appointment $appointment): array
{
    return $appointment->only(['customer_name', 'customer_phone', 'customer_email', 'notes']);
}

function moveAppointment(Appointment $appointment, string $startsAt, ?Service $service = null, ?array $customer = null, bool $ignoreHoursAndCapacity = false, ?CarbonImmutable $now = null): Appointment
{
    return app(RescheduleAppointment::class)->handle(
        $appointment,
        $service ?? test()->service,
        CarbonImmutable::parse($startsAt),
        $customer ?? unchangedCustomer($appointment),
        $ignoreHoursAndCapacity,
        $now ?? test()->now,
    );
}

test('it moves the same appointment to a free time, keeping its id and token', function () {
    $token = $this->appointment->token;

    moveAppointment($this->appointment, '2030-01-09 16:00');

    $moved = Appointment::sole();
    expect($moved->id)->toBe($this->appointment->id);
    expect($moved->token)->toBe($token);
    expect($moved->status)->toBe(AppointmentStatus::Confirmed);
    expect($moved->starts_at->format('Y-m-d H:i'))->toBe('2030-01-09 16:00');
    expect($moved->ends_at->format('Y-m-d H:i'))->toBe('2030-01-09 17:00');
});

test('it can move an appointment into the time it holds now', function () {
    // Capacity 1: only the appointment itself is in the way.
    moveAppointment($this->appointment, '2030-01-08 09:45');

    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('09:45');
});

test('it copies the name and duration of a new service', function () {
    $balayage = Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 150]);

    moveAppointment($this->appointment, '2030-01-08 12:00', $balayage);

    $moved = $this->appointment->fresh();
    expect($moved->service_id)->toBe($balayage->id);
    expect($moved->service_name)->toBe('Balayage');
    expect($moved->ends_at->format('H:i'))->toBe('14:30');
});

test('keeping the service keeps the duration and name the appointment was booked with', function () {
    $this->service->update(['name' => 'Corte renombrado', 'duration_minutes' => 90]);

    moveAppointment($this->appointment, '2030-01-08 12:00');

    $moved = $this->appointment->fresh();
    expect($moved->service_name)->toBe('Corte');
    expect($moved->ends_at->format('H:i'))->toBe('13:00');
});

test('it saves the customer details together with the new time', function () {
    moveAppointment($this->appointment, '2030-01-08 12:00', customer: [
        'customer_name' => ' Rosa Vidal Puig ',
        'customer_phone' => '611 222 333',
        'customer_email' => ' Rosa.Nueva@Example.TEST ',
        'notes' => null,
    ]);

    $moved = $this->appointment->fresh();
    expect($moved->starts_at->format('H:i'))->toBe('12:00');
    expect($moved->customer_name)->toBe('Rosa Vidal Puig');
    expect($moved->customer_phone)->toBe('611 222 333');
    expect($moved->customer_email)->toBe('rosa.nueva@example.test');
    expect($moved->notes)->toBeNull();
});

test('moving leaves no trace of the change in the appointment', function () {
    $before = $this->appointment->fresh()->only(['customer_name', 'customer_phone', 'customer_email', 'notes', 'source', 'token', 'customer_notified_at', 'salon_notified_at', 'cancelled_at']);

    moveAppointment($this->appointment, '2030-01-09 16:00');

    expect($this->appointment->fresh()->only(array_keys($before)))->toEqual($before);
});

test('a full or closed time is refused unless explicitly confirmed', function (string $time) {
    Appointment::factory()->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:00']);

    expect(fn () => moveAppointment($this->appointment, $time))->toThrow(SlotUnavailableException::class);
    expect($this->appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2030-01-08 10:00');

    moveAppointment($this->appointment, $time, ignoreHoursAndCapacity: true);
    expect($this->appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe($time);
})->with([
    'no capacity left' => '2030-01-08 12:30',
    'after closing time' => '2030-01-08 18:30',
    'closed monday' => '2030-01-14 10:00',
]);

test('a time in the past is refused even when confirmed', function (bool $ignoreHoursAndCapacity) {
    $now = CarbonImmutable::parse('2030-01-08 08:00');

    expect(fn () => moveAppointment($this->appointment, '2030-01-08 07:55', ignoreHoursAndCapacity: $ignoreHoursAndCapacity, now: $now))
        ->toThrow(StartTimeInPastException::class);

    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('10:00');
})->with(['without confirmation' => false, 'with confirmation' => true]);

test('a cancelled or already started appointment cannot be moved', function () {
    $cancelled = Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00']);

    expect(fn () => moveAppointment($cancelled, '2030-01-09 12:00'))->toThrow(AppointmentNotMovableException::class);
    expect(fn () => moveAppointment($this->appointment, '2030-01-08 12:00', now: CarbonImmutable::parse('2030-01-08 10:00')))
        ->toThrow(AppointmentNotMovableException::class);

    expect($cancelled->fresh()->starts_at->format('H:i'))->toBe('10:00');
    expect($this->appointment->fresh()->starts_at->format('H:i'))->toBe('10:00');
});

test('the row lock on the booking settings is the first query of the move transaction', function () {
    // Same reasoning as for creating an appointment: on MySQL (REPEATABLE
    // READ) only a locking read first lets the re-check see what a
    // concurrent request has just committed.
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    moveAppointment($this->appointment, '2030-01-08 12:00');

    expect($queries[0])->toContain('"booking_settings"');
});

test('a move sent after a concurrent cancellation changes nothing', function () {
    $cancelRequest = Appointment::find($this->appointment->id);
    $moveRequest = Appointment::find($this->appointment->id);

    app(CancelAppointment::class)->handle($cancelRequest);

    expect(fn () => moveAppointment($moveRequest, '2030-01-08 12:00'))->toThrow(AppointmentNotMovableException::class);

    $appointment = $this->appointment->fresh();
    expect($appointment->status)->toBe(AppointmentStatus::Cancelled);
    expect($appointment->starts_at->format('H:i'))->toBe('10:00');
});

test('two concurrent moves leave the whole result of the last one, never a mix', function () {
    $firstRequest = Appointment::find($this->appointment->id);
    $secondRequest = Appointment::find($this->appointment->id);

    moveAppointment($firstRequest, '2030-01-08 12:00', customer: ['customer_name' => 'Primera', 'customer_phone' => '600 000 001', 'customer_email' => null, 'notes' => 'Uno']);
    // The second request still holds the 10:00 copy, yet its own former
    // place (now 12:00) must not count against it either.
    moveAppointment($secondRequest, '2030-01-08 12:30', customer: ['customer_name' => 'Segunda', 'customer_phone' => '600 000 002', 'customer_email' => 'dos@example.test', 'notes' => null]);

    $appointment = Appointment::sole();
    expect($appointment->starts_at->format('H:i'))->toBe('12:30');
    expect($appointment->only(['customer_name', 'customer_phone', 'customer_email', 'notes']))
        ->toBe(['customer_name' => 'Segunda', 'customer_phone' => '600 000 002', 'customer_email' => 'dos@example.test', 'notes' => null]);
});

test('a cancellation after a move cancels the moved appointment', function () {
    $moveRequest = Appointment::find($this->appointment->id);
    $cancelRequest = Appointment::find($this->appointment->id);

    moveAppointment($moveRequest, '2030-01-08 12:00');

    expect(app(CancelAppointment::class)->handle($cancelRequest))->toBeTrue();
    $appointment = $this->appointment->fresh();
    expect($appointment->status)->toBe(AppointmentStatus::Cancelled);
    expect($appointment->starts_at->format('H:i'))->toBe('12:00');
});
