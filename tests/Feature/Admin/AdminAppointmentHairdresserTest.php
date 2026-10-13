<?php

use App\Mail\AppointmentRescheduledMail;
use App\Models\Appointment;
use App\Models\Hairdresser;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
 * The hairdresser in the panel's new and edit appointment forms (PRF-168,
 * PRF-169). "Now" is Tuesday 2030-01-08 08:00; open 09:00-19:00;
 * Jennifer and Isabel are active.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    $this->service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);
    $this->jennifer = Hairdresser::query()->where('name', 'Jennifer')->sole();
    $this->isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
});

function adminNewPayload(array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->service->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Rosa Vidal',
        'customer_phone' => '600123456',
        'customer_email' => '',
        'notes' => '',
        'hairdresser_id' => '',
    ], $overrides);
}

function adminEditPayload(Appointment $appointment, array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->service->id],
        'date' => $appointment->starts_at->toDateString(),
        'time' => $appointment->starts_at->format('H:i'),
        'customer_name' => $appointment->customer_name,
        'customer_phone' => $appointment->customer_phone,
        'customer_email' => $appointment->customer_email,
        'notes' => $appointment->notes,
        'hairdresser_id' => (string) ($appointment->hairdresser_id ?? ''),
        'version' => $appointment->fresh()->updated_at->getTimestamp(),
    ], $overrides);
}

function adminAppointment(array $attributes = []): Appointment
{
    return Appointment::factory()->withServices(test()->service)->create(array_merge([
        'starts_at' => '2030-01-08 10:00',
        'customer_email' => 'rosa@example.test',
        'customer_notified_at' => now(),
        'salon_notified_at' => now(),
    ], $attributes));
}

test('the new appointment form offers any hairdresser by default and each active one', function () {
    Hairdresser::factory()->inactive()->create(['name' => 'Laura']);

    $html = $this->get(route('admin.appointments.create'))
        ->assertOk()
        ->assertSeeInOrder(['Peluquera', 'Cualquiera', 'Jennifer', 'Isabel'])
        ->assertDontSee('Laura')
        ->getContent();

    expect($html)->toMatch('/<option value="" selected[^>]*>Cualquiera/');
});

test('the agenda\'s hairdresser filter preselects her in the new appointment form', function () {
    $html = $this->get(route('admin.appointments.create', ['peluquera' => $this->isabel->id]))->getContent();

    expect($html)->toMatch('/<option value="'.$this->isabel->id.'" selected/');
});

test('a new appointment can be for a hairdresser, who is recorded as the customer\'s choice', function () {
    $this->post(route('admin.appointments.store'), adminNewPayload(['hairdresser_id' => (string) $this->isabel->id]))->assertRedirect();
    $this->post(route('admin.appointments.store'), adminNewPayload(['time' => '12:00']))->assertRedirect();

    $appointments = Appointment::query()->orderBy('starts_at')->get();
    expect($appointments[0]->hairdresser_id)->toBe($this->isabel->id)
        ->and($appointments[0]->hairdresser_requested)->toBeTrue()
        ->and($appointments[1]->hairdresser_id)->toBeNull();
});

test('a new appointment for a busy or inactive hairdresser is refused', function () {
    adminAppointment(['hairdresser_id' => $this->isabel->id]);
    $laura = Hairdresser::factory()->inactive()->create(['name' => 'Laura']);

    $this->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), adminNewPayload(['hairdresser_id' => (string) $this->isabel->id, 'time' => '10:30']))
        ->assertSessionHasErrors(['time' => 'Esa hora no está disponible para este servicio con esa peluquera.']);
    $this->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), adminNewPayload(['hairdresser_id' => (string) $laura->id]))
        ->assertSessionHasErrors('hairdresser_id');

    expect(Appointment::count())->toBe(1);
});

test('the edit form shows her hairdresser, even an inactive one', function () {
    $laura = Hairdresser::factory()->create(['name' => 'Laura']);
    $appointment = adminAppointment(['hairdresser_id' => $laura->id]);
    $laura->update(['is_active' => false]);

    $html = $this->get(route('admin.appointments.edit', $appointment))->assertOk()->getContent();

    expect($html)->toMatch('/<option value="'.$laura->id.'" selected[^>]*>Laura · inactiva/');
});

test('assigning an unassigned appointment saves her and sends no email', function () {
    Mail::fake();
    $appointment = adminAppointment();

    $this->put(route('admin.appointments.update', $appointment), adminEditPayload($appointment, ['hairdresser_id' => (string) $this->jennifer->id]))
        ->assertSessionHas('status', 'Cita actualizada.');

    expect($appointment->fresh()->hairdresser_id)->toBe($this->jennifer->id)
        ->and($appointment->fresh()->hairdresser_requested)->toBeFalse();
    Mail::assertNothingSent();
});

test('changing the hairdresser the customer chose tells her by email', function () {
    Mail::fake();
    $appointment = adminAppointment(['hairdresser_id' => $this->jennifer->id, 'hairdresser_requested' => true]);

    $this->put(route('admin.appointments.update', $appointment), adminEditPayload($appointment, ['hairdresser_id' => (string) $this->isabel->id]))
        ->assertSessionHas('status', 'Cita actualizada. Se ha enviado el cambio a la clienta por correo.');

    Mail::assertSent(AppointmentRescheduledMail::class, fn (AppointmentRescheduledMail $mail) => str_contains($mail->render(), 'Isabel'));
});

test('a busy hairdresser warns, and "save anyway" confirms only that hairdresser', function () {
    $appointment = adminAppointment();
    adminAppointment(['hairdresser_id' => $this->isabel->id]);

    $this->from(route('admin.appointments.edit', $appointment))
        ->put(route('admin.appointments.update', $appointment), adminEditPayload($appointment, ['hairdresser_id' => (string) $this->isabel->id]))
        ->assertRedirect(route('admin.appointments.edit', $appointment))
        ->assertSessionHas('slot_warning', ['key' => $this->service->id.'|2030-01-08|10:00|'.$this->isabel->id, 'reason' => 'hairdresser_busy']);

    expect($appointment->fresh()->hairdresser_id)->toBeNull();

    // A confirmation for another hairdresser does not count.
    $this->from(route('admin.appointments.edit', $appointment))
        ->put(route('admin.appointments.update', $appointment), adminEditPayload($appointment, ['hairdresser_id' => (string) $this->isabel->id, 'force' => $this->service->id.'|2030-01-08|10:00|'.$this->jennifer->id]))
        ->assertSessionHas('slot_warning');

    $this->put(route('admin.appointments.update', $appointment), adminEditPayload($appointment, ['hairdresser_id' => (string) $this->isabel->id, 'force' => $this->service->id.'|2030-01-08|10:00|'.$this->isabel->id]))
        ->assertSessionHas('status');

    expect($appointment->fresh()->hairdresser_id)->toBe($this->isabel->id);
});

test('the warning for a busy hairdresser says so', function () {
    $appointment = adminAppointment();
    adminAppointment(['hairdresser_id' => $this->isabel->id]);

    $this->from(route('admin.appointments.edit', $appointment))
        ->put(route('admin.appointments.update', $appointment), adminEditPayload($appointment, ['hairdresser_id' => (string) $this->isabel->id]));

    $this->get(route('admin.appointments.edit', $appointment))
        ->assertSee('La peluquera elegida ya tiene otra cita a esa hora.');
});
