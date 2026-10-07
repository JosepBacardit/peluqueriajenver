<?php

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * PRF-149, PRF-150 (2026-10-07): the salon asked that the duration of a
 * service — and the appointment's end time, which would reveal it just as
 * well ("de 10:00 a 12:00" gives away a 2-hour balayage) — stay an
 * internal number, used only to work out free slots. Neither must reach
 * anything the customer sees: the booking page, the appointment page, nor
 * the 3 emails sent to the customer. The 2 emails sent to the salon and
 * the admin panel are unaffected: they keep showing both.
 */
beforeEach(function () {
    $this->balayage = Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 120, 'price_cents' => 9900]);
});

test('"/reservas" never shows the duration, not even in the page source, with no service chosen yet', function () {
    $html = $this->get(route('reservas'))->assertOk()->getContent();

    expect($html)->toContain('Balayage');
    expect($html)->not->toContain('2 h')->not->toContain('data-minutes');
});

test('"/reservas" never shows the duration or its total once a service is chosen', function () {
    $html = $this->get(route('reservas', ['servicio' => $this->balayage->id, 'fecha' => '2030-01-08']))
        ->assertOk()->getContent();

    expect($html)->toContain('Balayage');
    expect($html)->not->toContain('2 h')->not->toContain('data-minutes')->not->toContain('Duración total');
});

test('"/cita/{token}" never shows the duration, the total or the end time', function () {
    $appointment = Appointment::factory()->withServices($this->balayage)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 12:00',
    ]);

    $html = $this->get(route('cita.show', $appointment->token))->assertOk()->getContent();

    expect($html)->toContain('Balayage');
    expect($html)->toContain('10:00'); // the start time is shown...
    expect($html)->not->toContain('12:00'); // ...the end time never is
    expect($html)->not->toContain('2 h')->not->toContain('Duración total');
});

test('every email to the customer never shows the duration, the total or the end time', function (string $mailClass) {
    $appointment = Appointment::factory()->withServices($this->balayage)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 12:00',
    ]);

    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);
    $html = $mail->render();

    expect($html)->toContain('Balayage');
    expect($html)->toContain('10:00');
    expect($html)->not->toContain('12:00');
    expect($html)->not->toContain('2 h')->not->toContain('Duración total');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

test('the 2 emails to the salon keep showing the duration, the total and the end time', function (string $mailClass) {
    $appointment = Appointment::factory()->withServices($this->balayage)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 12:00',
    ]);

    $html = (new $mailClass($appointment))->render();

    expect($html)->toContain('Balayage (2 h)');
    expect($html)->toContain('Duración total');
    expect($html)->toContain('10:00–12:00');
})->with([
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
]);

test('the admin panel keeps showing the duration everywhere, unaffected', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route('admin.services.index'))->assertOk()->getContent();

    expect($html)->toContain('Balayage');
    expect($html)->toContain('2 h');
});

/*
 * PRF-158: waits inside a service (e.g. a dye's processing time) are as
 * internal as the duration. "Coloración" lasts 120 minutes, waiting from
 * minute 30 for 45 (10:30-11:15 for a 10:00 appointment). Matched as the
 * whole word "espera", since every customer email already says "Te
 * esperamos".
 */
function expectNoWaitRevealed(string $html): void
{
    expect($html)->toContain('Coloración');
    expect($html)->toContain('10:00');
    expect($html)->not->toMatch('/\bespera\b/iu');
    expect($html)
        ->not->toContain('10:30')
        ->not->toContain('11:15')
        ->not->toContain('12:00')
        ->not->toContain('45 min')
        ->not->toContain('2 h')
        ->not->toContain('data-wait-minutes')
        ->not->toContain('"minutes"');
}

function appointmentWithWait(): Appointment
{
    $coloracion = Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);

    return Appointment::factory()->withServices($coloracion)->create(['starts_at' => '2030-01-08 10:00']);
}

test('"/cita/{token}" never shows the waits of a service', function () {
    $appointment = appointmentWithWait();

    expect($appointment->waits)->toBe([['start' => 30, 'minutes' => 45]]);
    expectNoWaitRevealed($this->get(route('cita.show', $appointment->token))->assertOk()->getContent());
});

test('every email to the customer never shows the waits of a service', function (string $mailClass) {
    $appointment = appointmentWithWait();

    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);

    expectNoWaitRevealed($mail->render());
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

test('the whole-word check would catch a wait, but not "Te esperamos"', function () {
    expect('Te esperamos en Peluquería Jenver.')->not->toMatch('/\bespera\b/iu');
    expect('Espera: 10:30–11:15')->toMatch('/\bespera\b/iu');
    expect('2 h, incl. 45 min de espera')->toMatch('/\bespera\b/iu');
});
