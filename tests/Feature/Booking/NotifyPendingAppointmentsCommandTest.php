<?php

use App\Enums\AppointmentSource;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['booking.salon_notification_email' => 'salon@example.test']);
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    Mail::fake();
});

function pendingAppointment(array $attributes = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'starts_at' => '2030-01-10 10:00',
        'ends_at' => '2030-01-10 11:00',
        'customer_email' => 'nuria@example.test',
        'source' => AppointmentSource::Web,
        'customer_notified_at' => null,
        'salon_notified_at' => null,
        'created_at' => now()->subMinutes(10),
    ], $attributes));
}

test('it sends the notices that failed more than five minutes ago, and only once', function () {
    $appointment = pendingAppointment();

    $this->artisan('appointments:notify-pending')->assertExitCode(0);

    Mail::assertSent(AppointmentConfirmedMail::class, 1);
    Mail::assertSent(NewAppointmentMail::class, 1);
    expect($appointment->fresh()->customer_notified_at)->not->toBeNull();
    expect($appointment->fresh()->salon_notified_at)->not->toBeNull();

    $this->artisan('appointments:notify-pending')->assertExitCode(0);

    Mail::assertSent(AppointmentConfirmedMail::class, 1);
    Mail::assertSent(NewAppointmentMail::class, 1);
});

test('it only resends the notice that is still missing', function () {
    pendingAppointment(['customer_notified_at' => now()->subMinutes(9)]);

    $this->artisan('appointments:notify-pending');

    Mail::assertNotSent(AppointmentConfirmedMail::class);
    Mail::assertSent(NewAppointmentMail::class, 1);
});

test('it leaves alone recent, started, cancelled and fully notified appointments', function () {
    pendingAppointment(['created_at' => now()->subMinutes(2)]);
    pendingAppointment(['starts_at' => '2030-01-07 09:30', 'ends_at' => '2030-01-07 10:30']);
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-10 10:00', 'ends_at' => '2030-01-10 11:00', 'created_at' => now()->subMinutes(10)]);
    pendingAppointment(['customer_notified_at' => now(), 'salon_notified_at' => now()]);

    $this->artisan('appointments:notify-pending')->assertExitCode(0);

    Mail::assertNothingSent();
});

test('it never emails the salon about panel bookings', function () {
    pendingAppointment(['source' => AppointmentSource::Admin, 'customer_email' => null]);

    $this->artisan('appointments:notify-pending');

    Mail::assertNothingSent();
});
