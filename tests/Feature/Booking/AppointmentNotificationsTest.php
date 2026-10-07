<?php

use App\Enums\AppointmentSource;
use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['booking.salon_notification_email' => 'salon@example.test']);
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->service = Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 60, 'price_cents' => 8900]);
});

/**
 * @return array<string, mixed>
 */
function notificationBookingPayload(array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->service->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Núria Martínez',
        'customer_phone' => '600 123 456',
        'customer_email' => 'nuria@example.test',
        'notes' => 'Pelo rizado',
        'privacy' => '1',
    ], $overrides);
}

test('a web booking emails the customer their personal link without any price', function () {
    Mail::fake();

    $this->post(route('reservas.store'), notificationBookingPayload());
    $appointment = Appointment::sole();

    Mail::assertSent(AppointmentConfirmedMail::class, function (AppointmentConfirmedMail $mail) use ($appointment) {
        $html = $mail->render();

        return $mail->hasTo('nuria@example.test')
            && str_contains($html, route('cita.show', $appointment->token))
            && str_contains($html, 'Balayage')
            && str_contains($html, '10:00')
            && str_contains($html, 'C/ Lleida, 21')
            && str_contains($html, '633 912 050')
            && ! str_contains($html, '€')
            && ! str_contains($html, '89,00')
            && ! str_contains($html, '89.00');
    });

    expect($appointment->fresh()->customer_notified_at)->not->toBeNull();
});

test('a web booking emails the salon with every detail and a link to the agenda', function () {
    Mail::fake();

    $this->post(route('reservas.store'), notificationBookingPayload());
    $appointment = Appointment::sole();

    Mail::assertSent(NewAppointmentMail::class, function (NewAppointmentMail $mail) {
        $html = $mail->render();

        return $mail->hasTo('salon@example.test')
            && str_contains($html, 'Núria Martínez')
            && str_contains($html, '600 123 456')
            && str_contains($html, 'nuria@example.test')
            && str_contains($html, 'Pelo rizado')
            && str_contains($html, route('admin.agenda', ['fecha' => '2030-01-08']));
    });

    expect($appointment->fresh()->salon_notified_at)->not->toBeNull();
});

test('a panel booking does not email the salon and emails the customer only when there is an email', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $this->post(route('admin.appointments.store'), [
        'service_ids' => [$this->service->id], 'date' => '2030-01-08', 'time' => '10:00',
        'customer_name' => 'Sin Email', 'customer_phone' => '600 000 000', 'customer_email' => '',
    ]);
    $this->post(route('admin.appointments.store'), [
        'service_ids' => [$this->service->id], 'date' => '2030-01-08', 'time' => '12:00',
        'customer_name' => 'Con Email', 'customer_phone' => '600 000 001', 'customer_email' => 'con@example.test',
    ]);

    Mail::assertNotSent(NewAppointmentMail::class);
    Mail::assertSent(AppointmentConfirmedMail::class, 1);
    Mail::assertSent(AppointmentConfirmedMail::class, fn ($mail) => $mail->hasTo('con@example.test'));
});

test('a failing mail server never breaks the booking and leaves the notices pending', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP server unreachable'));

    $response = $this->post(route('reservas.store'), notificationBookingPayload());

    $appointment = Appointment::sole();
    $response->assertRedirect(route('cita.show', $appointment->token))->assertSessionHas('status', 'Tu cita está confirmada.');
    expect($appointment->customer_notified_at)->toBeNull();
    expect($appointment->salon_notified_at)->toBeNull();
});

test('when the customer cancels, both the customer and the salon are told', function () {
    Mail::fake();
    $appointment = Appointment::factory()->create(['customer_email' => 'nuria@example.test', 'starts_at' => '2030-01-10 10:00', 'ends_at' => '2030-01-10 11:00']);

    $this->post(route('cita.cancel', $appointment->token), ['confirm' => '1']);

    Mail::assertSent(AppointmentCancelledMail::class, fn (AppointmentCancelledMail $mail) => $mail->hasTo('nuria@example.test') && $mail->cancelledByCustomer);
    Mail::assertSent(CustomerCancelledAppointmentMail::class, fn ($mail) => $mail->hasTo('salon@example.test'));
});

test('when the salon cancels, the customer is told and invited to book again', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());
    $appointment = Appointment::factory()->create(['customer_email' => 'nuria@example.test', 'starts_at' => '2030-01-10 10:00', 'ends_at' => '2030-01-10 11:00']);

    $this->post(route('admin.appointments.cancel', $appointment));

    Mail::assertSent(AppointmentCancelledMail::class, function (AppointmentCancelledMail $mail) {
        return $mail->hasTo('nuria@example.test')
            && ! $mail->cancelledByCustomer
            && str_contains($mail->render(), route('reservas'));
    });
    Mail::assertNotSent(CustomerCancelledAppointmentMail::class);
});

test('a failing mail server never undoes a cancellation', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP server unreachable'));
    $appointment = Appointment::factory()->create(['starts_at' => '2030-01-10 10:00', 'ends_at' => '2030-01-10 11:00']);

    $this->post(route('cita.cancel', $appointment->token), ['confirm' => '1'])
        ->assertSessionHas('status', 'Tu cita se ha cancelado.');

    expect($appointment->fresh()->isConfirmed())->toBeFalse();
});

test('an appointment already cancelled sends nothing when cancelled again', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());
    $appointment = Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-10 10:00', 'ends_at' => '2030-01-10 11:00']);

    $this->post(route('admin.appointments.cancel', $appointment));

    Mail::assertNothingSent();
});

test('without a configured salon address the customer is still emailed', function () {
    Mail::fake();
    config(['booking.salon_notification_email' => null]);

    $this->post(route('reservas.store'), notificationBookingPayload());

    Mail::assertSent(AppointmentConfirmedMail::class);
    Mail::assertNotSent(NewAppointmentMail::class);
    expect(Appointment::sole()->salon_notified_at)->toBeNull();
});

test('the source of a web booking stays web for the notices', function () {
    Mail::fake();

    $this->post(route('reservas.store'), notificationBookingPayload());

    expect(Appointment::sole()->source)->toBe(AppointmentSource::Web);
});
