<?php

use App\Models\Appointment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * PRF-125, PRF-127, PRF-128: choosing several services on the public
 * booking page. "Now" is Monday 2030-01-07 10:00 (closed); Tuesday
 * 2030-01-08 is open 09:00-19:00. Default settings: capacity 2, every 15
 * minutes, 120 minutes of notice, 60 days ahead.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->haircut = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30, 'sort_order' => 1]);
    $this->beard = Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 15, 'sort_order' => 2]);
});

/**
 * @return array<string, mixed>
 */
function multiBookingPayload(array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->haircut->id, test()->beard->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Núria Martínez',
        'customer_phone' => '+34 600 123 456',
        'customer_email' => 'nuria@example.test',
        'notes' => null,
        'privacy' => '1',
        'website' => '',
    ], $overrides);
}

test('choosing two services computes the calendar, the hours and the total with their sum', function () {
    // Corte (30) + Barba (15) = 45 minutes: a 09:00 booking ends 09:45, so
    // a new one could not start before 09:45 at full capacity.
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 09:00', 'ends_at' => '2030-01-08 09:45']);

    $html = $this->get(route('reservas', ['servicio' => [$this->haircut->id, $this->beard->id], 'fecha' => '2030-01-08']))
        ->assertOk()->getContent();

    expect($html)->toContain('Corte + Barba');
    expect($html)->toContain('Duración total: 45 min');
    expect($html)->not->toContain('value="09:00"');
    expect($html)->not->toContain('value="09:30"'); // 09:30-10:15 would still overlap the 09:00-09:45 pair
    expect($html)->toContain('value="09:45"');
    expect($html)->toContain(e(route('reservas', ['servicio' => [$this->haircut->id, $this->beard->id], 'mes' => '2030-01', 'fecha' => '2030-01-08'])).'#horas');
});

test('the legacy single-service "?servicio=" link still works', function () {
    $html = $this->get(route('reservas', ['servicio' => $this->haircut->id, 'fecha' => '2030-01-08']))
        ->assertOk()->getContent();

    expect($html)->toContain('Corte');
    expect($html)->toContain('Duración total: 30 min');
});

test('more than the maximum number of services is rejected back to step 1', function () {
    $extra = Service::factory()->count(4)->create();
    $ids = $extra->pluck('id')->push($this->haircut->id)->push($this->beard->id)->all(); // 6 total

    $html = $this->get(route('reservas', ['servicio' => $ids]))->assertOk()->getContent();

    expect($html)->toContain(__('reservas.messages.invalid_services'));
});

test('a service that does not exist mixed with valid ones is rejected back to step 1, not silently dropped', function () {
    $html = $this->get(route('reservas', ['servicio' => [$this->haircut->id, 999999]]))->assertOk()->getContent();

    expect($html)->toContain(__('reservas.messages.invalid_services'));
    // The valid service is not booked on its own either: the whole
    // selection is rejected, per PRF-127/PRF-128 — back on step 1, not a
    // step-2 summary header.
    expect($html)->toContain(__('reservas.steps.service'));
    expect($html)->not->toContain(__('reservas.change_service'));
});

test('visiting the page with no "servicio" at all shows step 1 with no notice', function () {
    $html = $this->get(route('reservas'))->assertOk()->getContent();

    expect($html)->not->toContain(__('reservas.messages.invalid_services'));
});

test('posting two services creates one appointment with both', function () {
    $response = $this->post(route('reservas.store'), multiBookingPayload());

    $appointment = Appointment::sole();
    $response->assertRedirect(route('cita.show', $appointment->token));

    expect($appointment->items)->toHaveCount(2);
    expect($appointment->items->pluck('service_name')->all())->toBe(['Corte', 'Barba']);
    expect($appointment->starts_at->format('H:i'))->toBe('10:00');
    expect($appointment->ends_at->format('H:i'))->toBe('10:45');
    expect($appointment->services_label)->toBe('Corte + Barba');
});

test('a repeated service id in the submitted form is rejected, nothing is booked', function () {
    $this->post(route('reservas.store'), multiBookingPayload(['service_ids' => [$this->haircut->id, $this->haircut->id]]))
        ->assertSessionHasErrors('service_ids.0');

    expect(Appointment::count())->toBe(0);
});

test('more than the maximum number of services in the submitted form is rejected, nothing is booked', function () {
    $extra = Service::factory()->count(4)->create();
    $ids = $extra->pluck('id')->push($this->haircut->id)->push($this->beard->id)->all(); // 6 total

    $this->post(route('reservas.store'), multiBookingPayload(['service_ids' => $ids]))
        ->assertSessionHasErrors('service_ids');

    expect(Appointment::count())->toBe(0);
});

test('one non-bookable-online service mixed with a valid one rejects the whole booking', function () {
    $hidden = Service::factory()->notBookableOnline()->create();

    $this->post(route('reservas.store'), multiBookingPayload(['service_ids' => [$this->haircut->id, $hidden->id]]))
        ->assertSessionHasErrors(['time' => 'Esa hora ya no está disponible. Elige otra.']);

    expect(Appointment::count())->toBe(0);
});

test('an empty or missing service list in the submitted form is rejected, nothing is booked', function () {
    $this->post(route('reservas.store'), multiBookingPayload(['service_ids' => []]))
        ->assertSessionHasErrors('service_ids');

    expect(Appointment::count())->toBe(0);
});

test('the redirect back to the day after an error keeps the whole service selection', function () {
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45']);

    $this->post(route('reservas.store'), multiBookingPayload())
        ->assertRedirect(route('reservas', ['servicio' => [$this->haircut->id, $this->beard->id], 'mes' => '2030-01', 'fecha' => '2030-01-08']).'#horas')
        ->assertSessionHasErrors(['time' => 'Esa hora ya no está disponible. Elige otra.']);

    expect(Appointment::count())->toBe(2);
});

test('no price ever appears with two services selected', function () {
    Service::query()->update(['price_cents' => 9999]);

    $html = $this->get(route('reservas', ['servicio' => [$this->haircut->id, $this->beard->id], 'fecha' => '2030-01-08']))
        ->assertOk()->getContent();

    expect($html)->not->toContain('€')->not->toContain('99,99');
});
