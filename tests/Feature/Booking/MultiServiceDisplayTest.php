<?php

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * PRF-130: every email, the customer's own appointment page, and the
 * agenda show every service of an appointment with the total duration,
 * never a price.
 */
beforeEach(function () {
    $this->haircut = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30, 'price_cents' => 2500]);
    $this->beard = Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 15, 'price_cents' => 1000]);
    $this->appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45',
        'customer_name' => 'Rosa Vidal', 'customer_email' => 'rosa@example.test',
    ]);
});

test('every email lists both services with their own duration and the total, never a price', function (string $mailClass) {
    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($this->appointment, true) : new $mailClass($this->appointment);
    $html = $mail->render();

    expect($html)->toContain('Corte (30 min)');
    expect($html)->toContain('Barba (15 min)');
    expect($html)->toContain('Duración total');
    expect($html)->toContain('45 min');
    expect($html)->not->toContain('€')->not->toContain('25,00')->not->toContain('10,00');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

test('"/cita/{token}" lists both services with their own duration and the total, never a price', function () {
    $html = $this->get(route('cita.show', $this->appointment->token))->assertOk()->getContent();

    expect($html)->toContain('Corte (30 min)');
    expect($html)->toContain('Barba (15 min)');
    expect($html)->toContain('45 min');
    expect($html)->not->toContain('€')->not->toContain('25,00')->not->toContain('10,00');
});

test('the agenda card shows the summary and the total duration', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('Corte + Barba');
    expect($html)->toContain('(45 min)');
});

test('the grid block\'s title and aria-label carry the full detail, including the total duration', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('title="10:00–10:45 (45 min) Corte + Barba, Rosa Vidal"');
    expect($html)->toContain('aria-label="10:00 Corte + Barba, duración 45 min, Rosa Vidal, plaza 1"');
});

/**
 * Review finding (T049): a service name is customer-controlled text in
 * the salon's eyes too (it goes straight into the same Markdown emails),
 * so Markdown typed into a service's own name must never become a link
 * either — same protection MailContentEscapingTest already proves for
 * customer_name/notes (Markdown::withSecuredEncoding(),
 * AppServiceProvider).
 */
test('markdown in a service name never becomes a link in any booking email', function (string $mailClass) {
    $trap = Service::factory()->create(['name' => '[Pulsa aquí](https://evil.example/x)']);
    $appointment = Appointment::factory()->withServices($trap)->create();

    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);
    $html = $mail->render();

    expect($html)->not->toContain('href="https://evil.example');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);
