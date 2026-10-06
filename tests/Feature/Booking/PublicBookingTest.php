<?php

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * "Now" is Monday 2030-01-07 10:00 (closed day). Tuesday 2030-01-08 is
 * open 09:00-19:00. Default settings: capacity 2, every 15 minutes,
 * 120 minutes of notice, 60 days ahead.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->service = Service::factory()->create(['name' => 'Corte y peinado', 'duration_minutes' => 90, 'price_cents' => 3550, 'sort_order' => 1]);
});

/**
 * @return array<string, mixed>
 */
function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->service->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Núria Martínez',
        'customer_phone' => '+34 (600) 123-456',
        'customer_email' => 'nuria@example.test',
        'notes' => 'Tengo el pelo rizado',
        'privacy' => '1',
        'website' => '',
    ], $overrides);
}

test('the booking page lists the online services in order with their duration and no price', function () {
    Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 180, 'sort_order' => 0, 'price_cents' => 9900]);
    Service::factory()->notBookableOnline()->create(['name' => 'Alisado con diagnóstico']);
    Service::factory()->inactive()->create(['name' => 'Servicio retirado']);

    $response = $this->get(route('reservas'))->assertOk();

    $response->assertSeeInOrder(['Balayage', '3 h', 'Corte y peinado', '1 h 30 min'])
        ->assertDontSee('Alisado con diagnóstico')
        ->assertDontSee('Servicio retirado');

    expect($response->getContent())->not->toContain('€')->not->toContain('35,50')->not->toContain('99,00');
});

test('the calendar and the booking form never show the internal price', function () {
    $html = $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))->assertOk()->getContent();

    expect($html)->toContain('Confirmar cita')->not->toContain('€')->not->toContain('35,50')->not->toContain('35.50');
});

test('without online services the page invites to call or use whatsapp', function () {
    $this->service->update(['is_bookable_online' => false]);

    $this->get(route('reservas'))
        ->assertOk()
        ->assertSee('Ahora mismo no se pueden hacer reservas online. Llámanos al 633 912 050 o escríbenos por WhatsApp.');
});

test('choosing a service shows a month calendar where only days with free times can be picked', function () {
    $response = $this->get(route('reservas', ['servicio' => $this->service->id]))->assertOk();

    // Tuesday the 8th is open: it links to its times.
    $response->assertSee(route('reservas', ['servicio' => $this->service->id, 'mes' => '2030-01', 'fecha' => '2030-01-08']).'#horas');
    // Monday the 7th (today, closed) and Sunday the 13th cannot be picked.
    $response->assertDontSee('fecha=2030-01-07', false)->assertDontSee('fecha=2030-01-13', false);
    $response->assertSee('Enero 2030');
});

test('the calendar does not go before the current month nor past the last bookable day', function () {
    $this->get(route('reservas', ['servicio' => $this->service->id, 'mes' => '2029-12']))
        ->assertOk()
        ->assertSee('Enero 2030')
        ->assertDontSee('mes=2029-12', false);

    // 60 days from 2030-01-07 is 2030-03-08: March is the last month.
    $this->get(route('reservas', ['servicio' => $this->service->id, 'mes' => '2030-03']))
        ->assertOk()
        ->assertSee('Marzo 2030')
        ->assertSee('fecha=2030-03-08', false)
        ->assertDontSee('fecha=2030-03-09', false)
        ->assertDontSee('mes=2030-04', false);

    $this->get(route('reservas', ['servicio' => $this->service->id, 'mes' => '2030-05']))->assertSee('Marzo 2030');
});

test('choosing a day lists only its free times and the booking form', function () {
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $response = $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))->assertOk();

    $response->assertDontSee('value="09:00"', false) // 90 minutes would reach 10:00, already full
        ->assertDontSee('value="09:30"', false) // would overlap 10:00-11:00 at full capacity
        ->assertDontSee('value="10:30"', false)
        ->assertSee('value="11:00"', false)
        ->assertSee('value="17:30"', false)
        ->assertDontSee('value="17:45"', false) // would end after 19:00
        ->assertSee('He leído la información sobre protección de datos')
        ->assertSee('Responsable')
        ->assertSee('Finalidad')
        ->assertSee('Legitimación')
        ->assertSee('Destinatarios')
        ->assertSee('Derechos')
        ->assertSee('href="'.route('privacidad').'"', false);
});

/**
 * PRF-097: the calendar day cells and the time-slot buttons must meet the
 * 44px minimum touch target on a phone.
 */
test('the calendar days and the time slots meet the 44px minimum touch target', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $html = $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))->assertOk()->getContent();

    expect(substr_count($html, 'min-h-11 flex items-center justify-center border'))->toBeGreaterThan(30); // every day cell of the month, available or not, plus every time slot
});

/**
 * Review finding M3: the month grid groups its weeks under role="row" and
 * marks every day cell role="gridcell", completing the role="grid" ARIA
 * pattern it already declared (previously only role="columnheader" on the
 * weekday labels, with no row/gridcell structure underneath).
 */
test('the calendar grid groups its weeks in role rows and marks every day a gridcell', function () {
    // January 2030: starts on Tuesday, so 1 leading blank cell, 31 days
    // chunked into 5 weeks of up to 7 cells (no trailing padding) — 1
    // header row + 5 week rows, 31 gridcells, 1 presentation blank.
    $html = $this->get(route('reservas', ['servicio' => $this->service->id]))->assertOk()->getContent();

    expect(substr_count($html, 'role="row"'))->toBe(6);
    expect(substr_count($html, 'role="gridcell"'))->toBe(31);
    expect(substr_count($html, 'role="presentation"'))->toBe(1);
});

test('a customer books a free time and lands on the appointment page', function () {
    $response = $this->post(route('reservas.store'), bookingPayload());

    $appointment = Appointment::sole();
    $response->assertRedirect(route('cita.show', $appointment->token));

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
    expect($appointment->source)->toBe(AppointmentSource::Web);
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2030-01-08 10:00');
    expect($appointment->ends_at->format('H:i'))->toBe('11:30');
    expect($appointment->customer_name)->toBe('Núria Martínez');
    expect($appointment->customer_email)->toBe('nuria@example.test');
    expect($appointment->privacy_accepted_at)->not->toBeNull();

    $this->get(route('cita.show', $appointment->token))->assertSee('Tu cita está confirmada.');
});

test('invalid customer data is shown next to the field and nothing is booked', function (array $overrides, string $field) {
    $this->from(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))
        ->post(route('reservas.store'), bookingPayload($overrides))
        ->assertRedirect(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))
        ->assertSessionHasErrors($field)
        ->assertSessionHasInput('customer_phone');

    expect(Appointment::count())->toBe(0);
})->with([
    'missing name' => [['customer_name' => ''], 'customer_name'],
    'one-letter name' => [['customer_name' => 'N'], 'customer_name'],
    'name over 100' => [['customer_name' => str_repeat('a', 101)], 'customer_name'],
    'phone too short' => [['customer_phone' => '12'], 'customer_phone'],
    'phone with letters' => [['customer_phone' => '600 abc 456'], 'customer_phone'],
    'phone over 15 digits' => [['customer_phone' => '1234567890123456'], 'customer_phone'],
    'missing email' => [['customer_email' => ''], 'customer_email'],
    'invalid email' => [['customer_email' => 'nuria@'], 'customer_email'],
    'email over 150' => [['customer_email' => str_repeat('a', 140).'@example.test'], 'customer_email'],
    'notes over 500' => [['notes' => str_repeat('a', 501)], 'notes'],
    'privacy not accepted' => [['privacy' => ''], 'privacy'],
    'malformed time' => [['time' => '10h'], 'time'],
    'malformed date' => [['date' => '08/01/2030'], 'date'],
]);

test('manipulated requests for times that are not offered are refused', function (array $overrides) {
    $response = $this->post(route('reservas.store'), bookingPayload($overrides));

    $response->assertSessionHasErrors(['time' => 'Esa hora ya no está disponible. Elige otra.']);
    expect(Appointment::count())->toBe(0);
})->with([
    'off the 15-minute grid' => [['time' => '10:05']],
    'in the past' => [['date' => '2030-01-06', 'time' => '10:00']],
    'beyond the maximum advance' => [['date' => '2030-03-12', 'time' => '10:00']],
    'closed day' => [['date' => '2030-01-13', 'time' => '10:00']],
    'ends after closing' => [['time' => '18:00']],
]);

test('a time inside the minimum notice is refused', function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-08 09:00'));

    $this->post(route('reservas.store'), bookingPayload(['time' => '10:30']))
        ->assertSessionHasErrors(['time' => 'Esa hora ya no está disponible. Elige otra.']);

    expect(Appointment::count())->toBe(0);
});

test('a service that is not bookable online cannot be booked even by tampering', function () {
    $hidden = Service::factory()->notBookableOnline()->create();

    $this->post(route('reservas.store'), bookingPayload(['service_ids' => [$hidden->id]]))
        ->assertSessionHasErrors(['time' => 'Esa hora ya no está disponible. Elige otra.']);

    expect(Appointment::count())->toBe(0);
});

test('a time taken while the customer filled the form is refused keeping the data', function () {
    // The form was shown with 10:00 free; then two bookings took it.
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $this->post(route('reservas.store'), bookingPayload())
        ->assertRedirect(route('reservas', ['servicio' => $this->service->id, 'mes' => '2030-01', 'fecha' => '2030-01-08']).'#horas')
        ->assertSessionHasErrors(['time' => 'Esa hora ya no está disponible. Elige otra.'])
        ->assertSessionHasInput('customer_email', 'nuria@example.test');

    expect(Appointment::count())->toBe(2);
});

test('sending the same booking twice creates a single appointment', function () {
    $this->post(route('reservas.store'), bookingPayload());

    $this->post(route('reservas.store'), bookingPayload())
        ->assertSessionHasErrors(['customer_email' => 'Ya tienes una cita confirmada a esa hora.']);

    expect(Appointment::count())->toBe(1);
});

test('a filled honeypot looks successful but books nothing', function () {
    $this->post(route('reservas.store'), bookingPayload(['website' => 'http://spam.example']))
        ->assertRedirect(route('reservas'))
        ->assertSessionHas('status');

    expect(Appointment::count())->toBe(0);
});

test('more than five submissions a minute from one connection are refused', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('reservas.store'), bookingPayload(['customer_name' => '']));
    }

    $this->post(route('reservas.store'), bookingPayload())
        ->assertSessionHasErrors(['booking' => 'Demasiados intentos. Espera un minuto y vuelve a probar.']);

    expect(Appointment::count())->toBe(0);
});

test('the default minimum notice is respected on the day list', function () {
    BookingSetting::current()->update(['min_notice_minutes' => 120]);
    $this->travelTo(CarbonImmutable::parse('2030-01-08 10:05'));

    $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))
        ->assertDontSee('value="12:00"', false)
        ->assertSee('value="12:15"', false);
});

test('the month calendar needs a small, fixed number of queries', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get(route('reservas', ['servicio' => $this->service->id, 'mes' => '2030-01']))->assertOk();

    expect($queries)->toBeLessThan(15);
});

test('the basic data-protection notice marks the data controller as pending like the privacy policy', function () {
    $this->get(route('reservas', ['servicio' => $this->service->id, 'fecha' => '2030-01-08']))
        ->assertSee('[Pendiente de confirmar: nombre o razón social del titular]');
});
