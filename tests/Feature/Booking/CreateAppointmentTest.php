<?php

use App\Actions\CancelAppointment;
use App\Actions\CreateAppointment;
use App\Booking\DuplicateAppointmentException;
use App\Booking\SlotUnavailableException;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    BookingSetting::current()->update(['capacity' => 2, 'min_notice_minutes' => 0]);
    $this->now = CarbonImmutable::parse('2030-01-07 20:00');
    $this->service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);
});

/**
 * @return array{customer_name: string, customer_phone: string, customer_email: string|null, notes: string|null}
 */
function customerData(string $email = 'ana@example.test'): array
{
    return ['customer_name' => 'Ana', 'customer_phone' => '600 000 000', 'customer_email' => $email, 'notes' => null];
}

test('it creates a confirmed appointment copying the service name and duration', function () {
    $appointment = app(CreateAppointment::class)->handle(
        $this->service, CarbonImmutable::parse('2030-01-08 10:00'), customerData(), AppointmentSource::Web, applyPublicRules: true, now: $this->now,
    );

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
    expect($appointment->source)->toBe(AppointmentSource::Web);
    expect($appointment->service_name)->toBe('Corte');
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2030-01-08 10:00');
    expect($appointment->ends_at->format('Y-m-d H:i'))->toBe('2030-01-08 11:00');
    expect(strlen($appointment->token))->toBeGreaterThanOrEqual(40);
});

test('changing the service duration later does not change existing appointments', function () {
    $appointment = app(CreateAppointment::class)->handle(
        $this->service, CarbonImmutable::parse('2030-01-08 10:00'), customerData(), AppointmentSource::Web, applyPublicRules: true, now: $this->now,
    );

    $this->service->update(['duration_minutes' => 90, 'name' => 'Corte largo']);

    expect($appointment->fresh()->ends_at->format('H:i'))->toBe('11:00');
    expect($appointment->fresh()->service_name)->toBe('Corte');
});

test('it re-checks availability when saving, so a slot taken meanwhile is refused', function () {
    $start = CarbonImmutable::parse('2030-01-08 10:00');
    $action = app(CreateAppointment::class);

    // The slot was free when the customer saw it...
    $action->handle($this->service, $start, customerData('one@example.test'), AppointmentSource::Web, true, $this->now);
    $action->handle($this->service, $start, customerData('two@example.test'), AppointmentSource::Web, true, $this->now);

    // ...but two other bookings took both places before this one was sent.
    expect(fn () => $action->handle($this->service, $start, customerData('three@example.test'), AppointmentSource::Web, true, $this->now))
        ->toThrow(SlotUnavailableException::class);

    expect(Appointment::where('starts_at', $start)->count())->toBe(2);
});

test('the same email cannot hold two confirmed appointments at the same time', function () {
    $start = CarbonImmutable::parse('2030-01-08 10:00');
    $action = app(CreateAppointment::class);

    $action->handle($this->service, $start, customerData(), AppointmentSource::Web, true, $this->now);

    expect(fn () => $action->handle($this->service, $start, customerData('ANA@example.test'), AppointmentSource::Web, true, $this->now))
        ->toThrow(DuplicateAppointmentException::class);

    expect(Appointment::count())->toBe(1);
});

test('cancelling frees the slot and keeps the appointment', function () {
    $start = CarbonImmutable::parse('2030-01-08 10:00');
    BookingSetting::current()->update(['capacity' => 1]);
    $appointment = app(CreateAppointment::class)->handle($this->service, $start, customerData(), AppointmentSource::Web, true, $this->now);

    app(CancelAppointment::class)->handle($appointment);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
    expect($appointment->fresh()->cancelled_at)->not->toBeNull();
    expect(Appointment::count())->toBe(1);

    app(CreateAppointment::class)->handle($this->service, $start, customerData('other@example.test'), AppointmentSource::Web, true, $this->now);
    expect(Appointment::confirmed()->count())->toBe(1);
});
