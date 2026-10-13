<?php

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Models\Hairdresser;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Choosing a hairdresser on /reservas (PRF-166) and what the customer is
 * shown about her (PRF-167). "Now" is Monday 2030-01-07 10:00; Tuesday
 * 2030-01-08 is open 09:00-19:00; Jennifer and Isabel are active.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->service = Service::factory()->create(['name' => 'Corte y peinado', 'duration_minutes' => 60, 'sort_order' => 1]);
    $this->jennifer = Hairdresser::query()->where('name', 'Jennifer')->sole();
    $this->isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
});

function hairdresserBookingPayload(array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->service->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Núria Martínez',
        'customer_phone' => '600123456',
        'customer_email' => 'nuria@example.test',
        'notes' => null,
        'privacy' => '1',
        'website' => '',
    ], $overrides);
}

test('step 1 offers any hairdresser, checked by default, and each active hairdresser by name', function () {
    Hairdresser::factory()->inactive()->create(['name' => 'Laura']);

    $this->get(route('reservas'))
        ->assertOk()
        ->assertSeeInOrder(['Corte y peinado', 'Cualquier peluquera', 'Jennifer', 'Isabel'])
        ->assertSee('name="peluquera" value="" checked', false)
        ->assertDontSee('Laura');
});

test('the choice travels through the calendar and into the booking form', function () {
    $html = $this->get(route('reservas', ['servicio' => $this->service->id, 'peluquera' => $this->isabel->id, 'fecha' => '2030-01-08']))
        ->assertOk()
        ->assertSee('Con Isabel')
        ->assertSee('name="hairdresser_id" value="'.$this->isabel->id.'"', false)
        ->getContent();

    expect($html)->toContain('peluquera='.$this->isabel->id);
});

test('with any hairdresser the form sends none and says so', function () {
    $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))
        ->assertOk()
        ->assertSee('Cualquier peluquera')
        ->assertDontSee('name="hairdresser_id"', false);
});

test('an unknown or inactive hairdresser in the link is treated as any hairdresser', function (string $value) {
    Hairdresser::factory()->inactive()->create(['name' => 'Laura']);
    $value = $value === 'inactive' ? (string) Hairdresser::query()->where('name', 'Laura')->value('id') : $value;

    $this->get(route('reservas', ['servicio' => $this->service->id, 'peluquera' => $value, 'fecha' => '2030-01-08']))
        ->assertOk()
        ->assertSee('Cualquier peluquera')
        ->assertDontSee('name="hairdresser_id"', false);
})->with(['999', 'abc', 'inactive']);

test('the days and times offered are those of the chosen hairdresser', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 09:00', 'ends_at' => '2030-01-08 19:00', 'hairdresser_id' => $this->jennifer->id]);

    $this->get(route('reservas', ['servicio' => $this->service->id, 'peluquera' => $this->jennifer->id, 'fecha' => '2030-01-08']))
        ->assertDontSee('value="10:00"', false);

    $this->get(route('reservas', ['servicio' => $this->service->id, 'peluquera' => $this->isabel->id, 'fecha' => '2030-01-08']))
        ->assertSee('value="10:00"', false);

    $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))
        ->assertSee('value="10:00"', false);
});

test('a booking with a hairdresser is saved for her, and one without for anybody', function () {
    $this->post(route('reservas.store'), hairdresserBookingPayload(['hairdresser_id' => $this->isabel->id]))->assertRedirect();
    $this->post(route('reservas.store'), hairdresserBookingPayload(['time' => '12:00', 'customer_email' => 'otra@example.test', 'customer_phone' => '611222333']))->assertRedirect();

    $appointments = Appointment::query()->orderBy('starts_at')->get();
    expect($appointments[0]->hairdresser_id)->toBe($this->isabel->id)
        ->and($appointments[0]->hairdresser_requested)->toBeTrue()
        ->and($appointments[1]->hairdresser_id)->toBeNull();
});

test('a booking for a hairdresser who is busy or not active is refused', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'hairdresser_id' => $this->jennifer->id]);
    $laura = Hairdresser::factory()->inactive()->create(['name' => 'Laura']);

    $this->post(route('reservas.store'), hairdresserBookingPayload(['hairdresser_id' => $this->jennifer->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('time');
    $this->post(route('reservas.store'), hairdresserBookingPayload(['hairdresser_id' => $laura->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('time');
    $this->post(route('reservas.store'), hairdresserBookingPayload(['hairdresser_id' => 'abc']))
        ->assertSessionHasErrors('time');

    expect(Appointment::count())->toBe(1);
});

test('going back to a day after a refusal keeps the chosen hairdresser', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'hairdresser_id' => $this->jennifer->id]);

    $this->post(route('reservas.store'), hairdresserBookingPayload(['hairdresser_id' => $this->jennifer->id]))
        ->assertRedirectContains('peluquera='.$this->jennifer->id);
});

test('the customer sees the hairdresser she chose on her appointment page and in her emails', function () {
    $appointment = Appointment::factory()->withServices($this->service)->create(['starts_at' => '2030-01-08 10:00', 'hairdresser_id' => $this->isabel->id, 'hairdresser_requested' => true]);

    $this->get(route('cita.show', $appointment->token))->assertSee('Peluquera')->assertSee('Isabel');

    foreach ([new AppointmentConfirmedMail($appointment), new AppointmentRescheduledMail($appointment), new AppointmentCancelledMail($appointment, false)] as $mail) {
        expect($mail->render())->toContain('Peluquera:')->toContain('Isabel');
    }
});

test('the customer is never told about a hairdresser the salon assigned', function () {
    $appointment = Appointment::factory()->withServices($this->service)->create(['starts_at' => '2030-01-08 10:00', 'hairdresser_id' => $this->isabel->id, 'hairdresser_requested' => false]);

    $this->get(route('cita.show', $appointment->token))->assertDontSee('Isabel')->assertDontSee('Peluquera:');

    foreach ([new AppointmentConfirmedMail($appointment), new AppointmentRescheduledMail($appointment), new AppointmentCancelledMail($appointment, false)] as $mail) {
        expect($mail->render())->not->toContain('Peluquera:')->not->toContain('Isabel');
    }
});

test('the salon emails say which hairdresser, or that any can do it', function () {
    $assigned = Appointment::factory()->withServices($this->service)->create(['starts_at' => '2030-01-08 10:00', 'hairdresser_id' => $this->isabel->id]);
    $any = Appointment::factory()->withServices($this->service)->create(['starts_at' => '2030-01-08 12:00']);

    foreach ([NewAppointmentMail::class, CustomerCancelledAppointmentMail::class] as $mailClass) {
        expect((new $mailClass($assigned))->render())->toContain('Peluquera:')->toContain('Isabel');
        expect((new $mailClass($any))->render())->toContain('Peluquera:')->toContain('cualquiera (sin asignar)');
    }
});
