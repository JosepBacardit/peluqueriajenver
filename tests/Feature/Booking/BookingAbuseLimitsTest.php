<?php

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->service = Service::factory()->create(['duration_minutes' => 30]);
});

/**
 * @return array<string, mixed>
 */
function abusePayload(array $overrides = []): array
{
    return array_merge([
        'service_id' => test()->service->id,
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Ana',
        'customer_phone' => '600 123 456',
        'customer_email' => 'ana@example.test',
        'privacy' => '1',
    ], $overrides);
}

test('an email cannot hold more than two upcoming online appointments', function () {
    $this->post(route('reservas.store'), abusePayload(['time' => '10:00']));
    $this->post(route('reservas.store'), abusePayload(['time' => '11:00']));

    $this->post(route('reservas.store'), abusePayload(['time' => '12:00', 'customer_phone' => '611 111 111']))
        ->assertSessionHasErrors(['customer_email' => 'Ya tienes 2 citas pendientes. Para reservar otra, cancela una o llámanos al 633 912 050.']);

    expect(Appointment::count())->toBe(2);
});

test('a phone number cannot hold more than two upcoming online appointments whatever its format', function () {
    $this->post(route('reservas.store'), abusePayload(['time' => '10:00', 'customer_email' => 'one@example.test', 'customer_phone' => '600 123 456']));
    $this->post(route('reservas.store'), abusePayload(['time' => '11:00', 'customer_email' => 'two@example.test', 'customer_phone' => '+34 600-123-456']));

    $this->post(route('reservas.store'), abusePayload(['time' => '12:00', 'customer_email' => 'three@example.test', 'customer_phone' => '(600) 12 34 56']))
        ->assertSessionHasErrors('customer_email');

    expect(Appointment::count())->toBe(2);
});

test('cancelled and past appointments do not count towards the limit', function () {
    Appointment::factory()->cancelled()->create(['customer_email' => 'ana@example.test', 'starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 10:30']);
    Appointment::factory()->create(['customer_email' => 'ana@example.test', 'starts_at' => '2030-01-02 10:00', 'ends_at' => '2030-01-02 10:30']);
    Appointment::factory()->create(['customer_email' => 'ana@example.test', 'starts_at' => '2030-01-09 12:00', 'ends_at' => '2030-01-09 12:30']);

    $this->post(route('reservas.store'), abusePayload())->assertSessionHasNoErrors();

    expect(Appointment::confirmed()->where('starts_at', '>', now())->count())->toBe(2);
});

test('the salon can still book a customer with two upcoming appointments from the panel', function () {
    Appointment::factory()->count(2)->sequence(
        ['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 10:30'],
        ['starts_at' => '2030-01-09 11:00', 'ends_at' => '2030-01-09 11:30'],
    )->create(['customer_email' => 'ana@example.test']);

    $this->actingAs(User::factory()->create())
        ->post(route('admin.appointments.store'), [
            'service_id' => $this->service->id, 'date' => '2030-01-09', 'time' => '12:00',
            'customer_name' => 'Ana', 'customer_phone' => '600 123 456', 'customer_email' => 'ana@example.test',
        ])->assertSessionHasNoErrors();

    expect(Appointment::count())->toBe(3);
});

test('one connection cannot send more than ten bookings in a day', function () {
    foreach (range(1, 10) as $attempt) {
        // Spread over minutes so only the daily limit applies.
        $this->travel(2)->minutes();
        $this->post(route('reservas.store'), abusePayload(['customer_name' => '']));
    }

    $this->travel(2)->minutes();
    $this->post(route('reservas.store'), abusePayload())
        ->assertSessionHasErrors(['booking' => 'Se han hecho demasiadas reservas hoy desde esta conexión. Llámanos al 633 912 050.']);

    expect(Appointment::count())->toBe(0);
});
